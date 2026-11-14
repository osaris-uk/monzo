<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OAuth client
    |--------------------------------------------------------------------------
    |
    | Create a confidential client at https://developers.monzo.com to get these.
    | Only confidential clients receive refresh tokens, so pick that option if
    | you want the package to keep you signed in automatically.
    |
    | If redirect_uri is left empty, the package's own callback route
    | (route name "monzo.callback") is used.
    |
    */

    'client_id' => env('MONZO_CLIENT_ID'),

    'client_secret' => env('MONZO_CLIENT_SECRET'),

    'redirect_uri' => env('MONZO_REDIRECT_URI'),

    /*
    |--------------------------------------------------------------------------
    | Static access token
    |--------------------------------------------------------------------------
    |
    | Optional. A token pasted from the Monzo API playground. When set, it is
    | used instead of the token store and is never refreshed. Handy for quick
    | scripts; use the OAuth flow for anything long-running.
    |
    */

    'access_token' => env('MONZO_ACCESS_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Default account
    |--------------------------------------------------------------------------
    |
    | Used whenever an account ID is not passed explicitly, e.g.
    | Monzo::balance() or Monzo::transactions()->list().
    |
    */

    'account_id' => env('MONZO_ACCOUNT_ID'),

    /*
    |--------------------------------------------------------------------------
    | Endpoints
    |--------------------------------------------------------------------------
    */

    'api_url' => env('MONZO_API_URL', 'https://api.monzo.com'),

    'auth_url' => env('MONZO_AUTH_URL', 'https://auth.monzo.com'),

    /*
    |--------------------------------------------------------------------------
    | Token refresh
    |--------------------------------------------------------------------------
    |
    | When enabled, expired tokens are refreshed before a request is sent, and
    | a request that comes back 401 is retried once after a refresh. Refreshes
    | are guarded by a cache lock (when the cache store supports locks) because
    | Monzo invalidates the old refresh token as soon as a new one is issued.
    |
    | refresh_leeway is how many seconds before expiry a token counts as expired.
    |
    */

    'auto_refresh' => env('MONZO_AUTO_REFRESH', true),

    'refresh_leeway' => 60,

    /*
    |--------------------------------------------------------------------------
    | Token store
    |--------------------------------------------------------------------------
    |
    | Where the OAuth token is persisted between requests.
    |
    | Drivers: "database" (an encrypted row in monzo_tokens, the default),
    | "file" (an encrypted JSON file), "cache", "array" (in-memory, for
    | tests), or the class name of your own implementation of
    | Osaris\Monzo\Contracts\TokenStore -- to keep the token in a table the
    | app already has for its OAuth connections, for example. Name a class
    | here rather than in .env: \App\Support\Monzo\YourStore::class.
    |
    | The database is the default because it is the one every server and
    | container sees. Monzo invalidates the old refresh token on every
    | refresh, so with a store each process keeps its own copy of, the first
    | refresh signs every other copy out.
    |
    | With the database driver the package runs its own migration for the
    | table: `php artisan migrate` is all it needs. Set migrations to false
    | if you publish the migration (--tag=monzo-migrations) to change it.
    | Any other driver, and the migration does not run.
    |
    */

    'token_store' => [
        'driver' => env('MONZO_TOKEN_STORE', 'database'),
        'path' => storage_path('app/monzo/token.json'),
        'cache_store' => env('MONZO_TOKEN_CACHE_STORE'),
        'cache_key' => 'monzo.token',
        'connection' => env('MONZO_TOKEN_DB_CONNECTION'),
        'table' => 'monzo_tokens',
        'key' => 'default',
        'migrations' => env('MONZO_RUN_MIGRATIONS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP
    |--------------------------------------------------------------------------
    */

    'http' => [
        'timeout' => 30,
        'connect_timeout' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Attachments
    |--------------------------------------------------------------------------
    |
    | HTTP method used to send file bytes to the temporary upload_url returned
    | by /attachment/upload. Monzo's docs say POST, but the upload_url is a
    | pre-signed S3 URL, and those only accept the method they were signed
    | for (PUT for an object upload). Switch to POST if PUT is rejected.
    |
    */

    'attachments' => [
        'upload_method' => env('MONZO_ATTACHMENT_UPLOAD_METHOD', 'PUT'),
    ],

    /*
    |--------------------------------------------------------------------------
    | OAuth routes
    |--------------------------------------------------------------------------
    |
    | GET {prefix}/connect   -> redirects to Monzo  (route: monzo.connect)
    | GET {prefix}/callback  -> stores the token    (route: monzo.callback)
    |
    | Keep "auth" in the middleware: whoever completes this flow becomes the
    | Monzo account this app talks to.
    |
    */

    'routes' => [
        'enabled' => env('MONZO_ROUTES_ENABLED', true),
        'prefix' => 'monzo',
        'middleware' => ['web', 'auth'],
        'redirect_after' => '/',
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhooks
    |--------------------------------------------------------------------------
    |
    | POST {path}/{secret?}  (route: monzo.webhook)
    |
    | Monzo does not sign webhook payloads. Set a long random secret and it
    | becomes part of the URL you register (php artisan monzo:webhooks register).
    | Requests without the right secret get a 403. Until a secret is set, every
    | request is rejected.
    |
    */

    'webhooks' => [
        'enabled' => env('MONZO_WEBHOOKS_ENABLED', true),
        'path' => 'monzo/webhook',
        'secret' => env('MONZO_WEBHOOK_SECRET'),
        'middleware' => [],
    ],

];
