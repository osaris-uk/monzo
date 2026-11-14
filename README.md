# osaris-uk/monzo

[![Latest version](https://img.shields.io/packagist/v/osaris-uk/monzo.svg)](https://packagist.org/packages/osaris-uk/monzo)
[![Tests](https://github.com/osaris-uk/monzo/actions/workflows/tests.yml/badge.svg)](https://github.com/osaris-uk/monzo/actions/workflows/tests.yml)
[![License](https://img.shields.io/packagist/l/osaris-uk/monzo.svg)](LICENSE)

A Laravel package for the [Monzo Developer API](https://docs.monzo.com). It covers every documented endpoint, refreshes tokens automatically, works with business accounts, and includes OAuth routes, webhook events and Artisan commands.

> **Who it's for:** the Monzo Developer API only lets you connect your own account or a small set of users you explicitly allow. It isn't meant for public apps. Those need Monzo's Open Banking API, which requires FCA authorisation.

## Coverage

| Area | Endpoints | Package |
|---|---|---|
| OAuth | `auth.monzo.com`, `POST /oauth2/token` (code + refresh), `POST /oauth2/logout` | `Monzo::oauth()`, routes, `monzo:authorize`, `Monzo::logout()` |
| Ping | `GET /ping/whoami` | `Monzo::whoami()` |
| Accounts | `GET /accounts` | `Monzo::accounts()` – including `uk_business` |
| Balance | `GET /balance` | `Monzo::balance()` |
| Pots | `GET /pots`, `PUT /pots/{id}/deposit`, `PUT /pots/{id}/withdraw` | `Monzo::pots()` |
| Transactions | `GET /transactions`, `GET /transactions/{id}`, `PATCH /transactions/{id}` | `Monzo::transactions()` – paging, `expand[]=merchant`, metadata, notes |
| Feed | `POST /feed` | `Monzo::feed()` |
| Attachments | `POST /attachment/upload`, `/register`, `/deregister` | `Monzo::attachments()` – one-call upload |
| Receipts | `PUT`, `GET`, `DELETE /transaction-receipts` | `Monzo::receipts()` + fluent builders |
| Webhooks | `POST /webhooks`, `GET /webhooks`, `DELETE /webhooks/{id}`, `transaction.created` | `Monzo::webhooks()`, route, events |
| Errors | 400/401/403/404/405/429/5xx | Typed exceptions |

Requires PHP 8.2+ and Laravel 11, 12 or 13.

> **Laravel 11:** the package still installs on Laravel 11, but CI only tests Laravel 12 and 13, because the Laravel 11 jobs fail on a security advisory affecting Laravel 11 dependencies. It should work, but it isn't tested. If you're still on Laravel 11, upgrading is recommended.

## Install

The package is on [Packagist](https://packagist.org/packages/osaris-uk/monzo). Install it with Composer:

```bash
composer require osaris-uk/monzo
```

Laravel finds the service provider and the `Monzo` facade automatically. To change any defaults, publish the config file to `config/monzo.php`:

```bash
php artisan vendor:publish --tag=monzo-config
```

## Configure

Create a **confidential** client at [developers.monzo.com](https://developers.monzo.com). Only confidential clients get refresh tokens. Set the redirect URL to `https://your-app.test/monzo/callback`.

```dotenv
MONZO_CLIENT_ID=oauth2client_...
MONZO_CLIENT_SECRET=mnzconf....
MONZO_REDIRECT_URI=https://your-app.test/monzo/callback   # optional, defaults to the package route
MONZO_ACCOUNT_ID=acc_...                                  # optional default account
MONZO_WEBHOOK_SECRET=some-long-random-string              # required for the webhook route
```

## Connect your account

**From the browser:** sign in to your app and visit `/monzo/connect` (route `monzo.connect`). By default the route sits behind `web` + `auth`. Keep it that way, because whoever completes the flow becomes the account the app talks to.

**From the terminal:**

```bash
php artisan monzo:authorize
```

Open the URL it prints, click the email link from Monzo, then paste the redirect URL back into the terminal.

In both cases, **approve access in the Monzo app** afterwards. Until you do, most calls return `403`.

Then:

```bash
php artisan monzo:whoami
php artisan monzo:accounts --balances
```

Copy your business account's ID into `MONZO_ACCOUNT_ID` if you want it as the default.

> Once 5 minutes have passed since authentication, Monzo only lets you sync the **last 90 days** of transactions. If you need full history, pull it straight after connecting by passing an early date, e.g. `Monzo::transactions()->all(since: '2015-01-01T00:00:00Z')`.

### Tokens

Tokens are stored **encrypted in the database**, in a `monzo_tokens` table. The package runs its own migration, so all it needs is:

```bash
php artisan migrate
```

The database is the default because every server and container sees the same row. Monzo invalidates the old refresh token on every refresh, so with a store each process keeps its own copy of, such as a file on a disk the web server and queue worker don't share, the first refresh signs every other copy out. Refreshes are guarded by a lock on your default cache store, so when more than one process calls Monzo, that store must be shared too (Redis, Memcached or the database cache, not `file` or `array`).

To change the migration, publish it and stop the package running its own:

```bash
php artisan vendor:publish --tag=monzo-migrations
```

```dotenv
MONZO_RUN_MIGRATIONS=false
MONZO_TOKEN_DB_CONNECTION=    # optional, defaults to your default connection
```

On a single server you can keep the token in an encrypted file instead, at `storage/app/monzo/token.json`, with `MONZO_TOKEN_STORE=file`. You can also use `cache` or `array`, or give the class name of your own `Osaris\Monzo\Contracts\TokenStore` in `monzo.token_store.driver`.

#### Keeping the token in your own OAuth integrations table

Monzo is only OAuth, so if your app already keeps tokens for other services in one table, the Monzo token can live there too. Give the package a class implementing `Osaris\Monzo\Contracts\TokenStore` and it uses that instead. The package then doesn't run its own migration, so you won't get an empty `monzo_tokens` table.

Working example files are in [`examples/oauth-integrations`](examples/oauth-integrations), and the package's own tests run them as written.

1. **The table.** Copy [`2026_11_14_000000_create_oauth_integrations_table.php`](examples/oauth-integrations/2026_11_14_000000_create_oauth_integrations_table.php) into `database/migrations`. It has one row per `provider` and `account`, so `monzo` sits beside any other service, and you can connect more than one Monzo account if you need to. If you already have a table like this, skip this step and adjust the next two to its columns.

2. **The model.** Copy [`OAuthIntegration.php`](examples/oauth-integrations/OAuthIntegration.php) into `app/Models`. Its `encrypted` casts keep the tokens encrypted with `APP_KEY`, and `$hidden` keeps them out of arrays, JSON and logs.

3. **The store.** Copy [`MonzoIntegrationTokenStore.php`](examples/oauth-integrations/MonzoIntegrationTokenStore.php) into `app/Support/Monzo`. It implements three methods:

   ```php
   public function get(): ?AccessToken;          // the stored token, or null
   public function put(AccessToken $token): void; // after connecting, and after every refresh
   public function forget(): void;               // on monzo:logout
   ```

   `put()` must replace the existing row, not add another. Monzo invalidates the old refresh token on every refresh, so only the newest one works.

4. **Point the package at it.** Publish the config (`php artisan vendor:publish --tag=monzo-config`), then name the class in `config/monzo.php`. Use the config file rather than `.env`, because a class name is code, not a setting:

   ```php
   'token_store' => [
       'driver' => \App\Support\Monzo\MonzoIntegrationTokenStore::class,
       // ...
   ],
   ```

5. **Migrate and connect** as usual:

   ```bash
   php artisan migrate
   php artisan monzo:authorize
   ```

The package resolves your store from the container, so its constructor can ask for whatever it needs. To keep a second Monzo account, bind another instance with a different `account`. If the class doesn't exist or doesn't implement `TokenStore`, the package throws a `ConfigurationException` naming it.

Expired tokens are refreshed before a request, and a `401` triggers one refresh and a retry. Monzo invalidates the old refresh token on every refresh, so refreshes run under a cache lock. If the app sits idle, schedule a refresh so the token never goes stale:

```php
// routes/console.php
Schedule::command('monzo:refresh')->hourly();
```

For quick scripts you can skip OAuth: set `MONZO_ACCESS_TOKEN` to a playground token, or call `Monzo::withToken('...')`.

## Usage

```php
use Osaris\Monzo\Facades\Monzo;
```

You can also inject `Osaris\Monzo\Monzo`.

### Accounts and balance

```php
$accounts = Monzo::accounts()->list();         // every account, business included
$business = Monzo::accounts()->business()->first();

$business->id; $business->sortCode; $business->accountNumber; $business->isBusiness();

$balance = Monzo::balance($business->id);      // or Monzo::balance() for the default account
$balance->balance;       // 12345 (pennies)
$balance->amount();      // 123.45
$balance->totalBalance;  // includes pots
$balance->spendToday;

// Use a different default account for a block of calls
$biz = Monzo::forAccount($business->id);
$biz->balance();
$biz->transactions()->list();
```

### Transactions

```php
// One page (up to 100)
$page = Monzo::transactions()->list(since: now()->subWeek(), limit: 100, expandMerchant: true);

// Everything in a window, fetched lazily 100 at a time
Monzo::transactions()->all(since: now()->subDays(90))
    ->filter->isDebit()
    ->each(fn ($tx) => logger($tx->description, [$tx->value()]));

$tx = Monzo::transactions()->find('tx_...');   // merchant expanded by default
$tx->merchant?->name;
$tx->isPending(); $tx->isDeclined(); $tx->declineReason; $tx->category;

Monzo::transactions()->annotate('tx_...', ['invoice' => 'INV-0042', 'project' => 'website']);
Monzo::transactions()->notes('tx_...', 'Client lunch – Acme Ltd');
Monzo::transactions()->forgetMetadata('tx_...', 'project');
```

`since` accepts a date or a transaction ID. `before` accepts a date. If you leave out `since`, it defaults to 89 days ago, which keeps the request inside Monzo's 90-day window.

### Pots

```php
$pots = Monzo::pots()->list();              // deleted pots hidden (pass includeDeleted: true)
$tax  = Monzo::pots()->findByName('Tax');

Monzo::pots()->deposit($tax->id, 25000);   // £250 from the default account
Monzo::pots()->withdraw($tax->id, 5000, dedupeId: $myStableId);
```

Every move sends a `dedupe_id`. If you don't pass one, a UUID is generated. Pass your own stable ID when you retry so the same transfer can't run twice. Monzo doesn't allow API withdrawals from pots with added security.

### Feed items

```php
use Osaris\Monzo\Data\FeedItem;

Monzo::feed()->create(
    FeedItem::make('Invoice paid', 'https://example.com/icon.png')
        ->body('Acme Ltd paid £1,200.00')
        ->url('https://example.com/invoices/42')
        ->backgroundColor('#FCF1EE')
        ->titleColor('#333333')
);

Monzo::feed()->basic('Deploy finished', 'https://example.com/ok.png', body: 'v2.3.1 is live');
```

### Attachments

```php
// Path, SplFileInfo or UploadedFile: upload + register in one call
$attachment = Monzo::attachments()->upload('tx_...', storage_path('receipts/hardware.pdf'));

// Raw contents
Monzo::attachments()->upload('tx_...', $pdfBytes, fileName: 'receipt.pdf', fileType: 'application/pdf');

// A file you host yourself
Monzo::attachments()->register('tx_...', 'https://files.example.com/r.jpg', 'image/jpeg');

Monzo::attachments()->deregister($attachment->id);
```

The low-level steps are also available: `requestUpload()`, `sendToUploadUrl()`, `register()`. File bytes are sent to Monzo's pre-signed upload URL with `PUT`. Monzo's docs say `POST`, so if `PUT` is rejected, set `MONZO_ATTACHMENT_UPLOAD_METHOD=POST`.

### Receipts

```php
use Osaris\Monzo\Data\Receipts\{Receipt, ReceiptItem, ReceiptTax, ReceiptPayment, ReceiptMerchant};

$receipt = Receipt::make('tx_...', externalId: 'order-1234', total: 1299)
    ->addItem(ReceiptItem::make('Flat white', 299)->quantity(1)->tax(50))
    ->addItem(ReceiptItem::make('Meal deal', 1000)
        ->addSubItem(ReceiptItem::make('Sandwich', 600))
        ->addSubItem(ReceiptItem::make('Drink', 400)))
    ->addTax(ReceiptTax::make('VAT', 217)->taxNumber('GB123456789'))
    ->addPayment(ReceiptPayment::card(1299, lastFour: '4242'))
    ->merchant(ReceiptMerchant::make('Corner Café')->online(false)->storePostcode('LN1 1AA'));

Monzo::receipts()->create($receipt);       // the same external_id again = update
Monzo::receipts()->find('order-1234');     // array
Monzo::receipts()->delete('order-1234');
```

Amounts are in pennies. Sub-items can only go one level deep, and their amounts should add up to the parent item's amount. You can also pass a raw array to `create()`.

### Webhooks

```bash
php artisan monzo:webhooks register        # registers this app's /monzo/webhook/{secret} URL
php artisan monzo:webhooks list
php artisan monzo:webhooks delete --id=webhook_...
php artisan monzo:webhooks clear
```

```php
Monzo::webhooks()->register('https://example.com/hook');
Monzo::webhooks()->ensure($url);           // register only if it's missing
Monzo::webhooks()->list();
Monzo::webhooks()->delete('webhook_...');
```

The package's route (`POST /monzo/webhook/{secret?}`, named `monzo.webhook`) checks the secret and fires events. **Set `MONZO_WEBHOOK_SECRET` first:** until it's set, the route rejects every request with a `403`, and `monzo:webhooks register` refuses to run.

```php
use Osaris\Monzo\Events\TransactionCreated;
use Osaris\Monzo\Events\WebhookReceived;

Event::listen(function (TransactionCreated $event) {
    $tx = $event->transaction;   // Osaris\Monzo\Data\Transaction, merchant expanded
});

Event::listen(function (WebhookReceived $event) {
    // $event->type, $event->data, $event->payload – every webhook type
});
```

Monzo doesn't sign webhooks, which is why the secret lives in the URL. Monzo retries failed deliveries up to 5 times, so make your listeners idempotent (key them on `$tx->id`). For slow work, use queued listeners.

Because the secret is part of the URL, **it ends up anywhere URLs are logged**: your web server's access log, a load balancer or CDN, and error trackers that record the request URL. Anyone who can read those logs can post fake events. Keep those logs private, strip or mask the path in them if you can, and rotate the secret if they leak: set a new `MONZO_WEBHOOK_SECRET`, then run `php artisan monzo:webhooks clear` and `php artisan monzo:webhooks register`. Treat webhook events as a prompt to fetch the transaction, not as the source of truth: `Monzo::transactions()->find($event->transaction->id)` only returns what Monzo really has.

### Other events

- `MonzoConnected`: fired after the OAuth callback stores a token.
- `TokenRefreshed`: fired whenever a refresh happens.

### Errors

Every failed call throws a subclass of `Osaris\Monzo\Exceptions\MonzoException`:

| Status | Exception |
|---|---|
| 400 | `BadRequestException` |
| 401 | `AuthenticationException` (after one refresh-and-retry) |
| 403 | `ForbiddenException`: often means access hasn't been approved in the app yet |
| 404 | `NotFoundException` |
| 405 | `MethodNotAllowedException` |
| 429 | `RateLimitException` |
| 5xx | `ServerException` |

Each exception exposes `->status()`, `->errorCode` (Monzo's `code`, or the OAuth `error`, e.g. `invalid_token`), `->body` and `->response`. Setup problems throw `ConfigurationException` or `TokenNotFoundException`. If a stored token can't be decrypted (usually because `APP_KEY` changed), you get `TokenDecryptionException`. Reconnect to replace it.

### Raw access

```php
Monzo::client()->get('/some/new/endpoint', ['account_id' => '...']);
Monzo::client()->post('/...', [...]);      // form-encoded
Monzo::client()->putJson('/...', [...]);
```

Every response object keeps the full payload, so undocumented fields are still there: `$tx->get('counterparty.name')`, `$tx['local_amount']`, `$tx->toArray()`.

## Artisan

| Command | What it does |
|---|---|
| `monzo:authorize` | Connect an account from the terminal |
| `monzo:whoami` | Show the token's user, client and expiry |
| `monzo:accounts [--balances] [--type=] [--closed]` | List accounts |
| `monzo:refresh [--force]` | Refresh the token (schedule it) |
| `monzo:webhooks list\|register\|delete\|clear` | Manage webhooks |
| `monzo:logout` | Revoke the token and remove it |

## Testing

```bash
composer install
composer test
```

The tests use `Http::fake()` and never call Monzo. In your own app, fake `api.monzo.com/*` the same way.

## Security

Please report vulnerabilities privately. See [SECURITY.md](SECURITY.md).

## License

MIT. See [LICENSE](LICENSE).
