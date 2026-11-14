<?php

namespace Osaris\Monzo\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Osaris\Monzo\Data\FeedItem;
use Osaris\Monzo\Data\Receipts\Receipt;
use Osaris\Monzo\Data\Receipts\ReceiptItem;
use Osaris\Monzo\Data\Receipts\ReceiptMerchant;
use Osaris\Monzo\Data\Receipts\ReceiptPayment;
use Osaris\Monzo\Data\Receipts\ReceiptTax;
use Osaris\Monzo\Enums\AccountType;
use Osaris\Monzo\Exceptions\RateLimitException;
use Osaris\Monzo\Facades\Monzo;
use Osaris\Monzo\Tests\TestCase;

class ResourcesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->storeToken();
    }

    public function test_accounts_including_business(): void
    {
        Http::fake(['api.monzo.com/accounts*' => Http::response(['accounts' => [
            ['id' => 'acc_1', 'description' => 'user_1', 'created' => '2024-01-01T00:00:00.000Z', 'type' => 'uk_retail', 'sort_code' => '040004', 'account_number' => '12345678'],
            ['id' => 'acc_2', 'description' => 'Osaris Ltd', 'created' => '2024-02-01T00:00:00.000Z', 'type' => 'uk_business'],
        ]])]);

        $accounts = Monzo::accounts()->list();

        $this->assertCount(2, $accounts);
        $this->assertSame('040004', $accounts[0]->sortCode);
        $this->assertTrue($accounts[1]->isBusiness());
        $this->assertSame(AccountType::Business, $accounts[1]->accountType());
        $this->assertSame(['acc_2'], Monzo::accounts()->business()->pluck('id')->all());

        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.monzo.com/accounts'
            && $r->hasHeader('Authorization', 'Bearer access_1'));
    }

    public function test_accounts_type_filter(): void
    {
        Http::fake(['api.monzo.com/accounts*' => Http::response(['accounts' => []])]);

        Monzo::accounts()->list(AccountType::RetailJoint);

        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.monzo.com/accounts?account_type=uk_retail_joint');
    }

    public function test_balance_uses_default_account(): void
    {
        Http::fake(['api.monzo.com/balance*' => Http::response([
            'balance' => 5000, 'total_balance' => 6000, 'currency' => 'GBP', 'spend_today' => -120,
        ])]);

        $balance = Monzo::balance();

        $this->assertSame(5000, $balance->balance);
        $this->assertSame(6000, $balance->totalBalance);
        $this->assertSame(50.0, $balance->amount());
        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.monzo.com/balance?account_id=acc_default');
    }

    public function test_for_account_overrides_default(): void
    {
        Http::fake(['api.monzo.com/balance*' => Http::response(['balance' => 1, 'currency' => 'GBP'])]);

        Monzo::forAccount('acc_business')->balance();

        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.monzo.com/balance?account_id=acc_business');
    }

    public function test_pots_list_deposit_withdraw(): void
    {
        $pot = ['id' => 'pot_1', 'name' => 'Tax', 'style' => 'beach_ball', 'balance' => 1000, 'currency' => 'GBP',
            'created' => '2024-01-01T00:00:00Z', 'updated' => '2024-01-02T00:00:00Z', 'deleted' => false];

        Http::fake([
            'api.monzo.com/pots?*' => Http::response(['pots' => [$pot, ['id' => 'pot_old', 'name' => 'Old', 'deleted' => true]]]),
            'api.monzo.com/pots/pot_1/deposit' => Http::response(['balance' => 1500] + $pot),
            'api.monzo.com/pots/pot_1/withdraw' => Http::response(['balance' => 500] + $pot),
        ]);

        $this->assertSame(['pot_1'], Monzo::pots()->list()->pluck('id')->all());
        $this->assertSame('pot_1', Monzo::pots()->findByName('tax')->id);

        $this->assertSame(1500, Monzo::pots()->deposit('pot_1', 500, dedupeId: 'dd_1')->balance);
        Http::assertSent(fn (Request $r) => $r->method() === 'PUT'
            && str_ends_with($r->url(), '/pots/pot_1/deposit')
            && $r['source_account_id'] === 'acc_default'
            && $r['amount'] === '500'
            && $r['dedupe_id'] === 'dd_1');

        Monzo::pots()->withdraw('pot_1', 500, 'acc_x');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/pots/pot_1/withdraw')
            && $r['destination_account_id'] === 'acc_x'
            && is_string($r['dedupe_id']) && $r['dedupe_id'] !== '');
    }

    public function test_pot_amount_must_be_positive(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Monzo::pots()->deposit('pot_1', 0);
    }

    public function test_transactions_list_with_expand_and_dates(): void
    {
        Http::fake(['api.monzo.com/transactions*' => Http::response(['transactions' => [
            self::transaction('tx_1', ['merchant' => ['id' => 'merch_1', 'name' => 'Tesco']]),
        ]])]);

        $txs = Monzo::transactions()->list(
            since: new \DateTimeImmutable('2024-01-01 10:00:00', new \DateTimeZone('UTC')),
            limit: 50,
            expandMerchant: true,
        );

        $this->assertSame('Tesco', $txs[0]->merchant->name);
        $this->assertSame('merch_1', $txs[0]->merchantId);

        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.monzo.com/transactions?account_id=acc_default&since=2024-01-01T10%3A00%3A00Z&limit=50&expand%5B%5D=merchant');
    }

    public function test_transactions_all_paginates_with_since_cursor(): void
    {
        $page1 = array_map(fn ($i) => self::transaction("tx_{$i}"), range(1, 2));
        $page2 = [self::transaction('tx_3')];

        Http::fake(['api.monzo.com/transactions*' => Http::sequence()
            ->push(['transactions' => $page1])
            ->push(['transactions' => $page2])]);

        $ids = Monzo::transactions()->all(pageSize: 2)->map->id->all();

        $this->assertSame(['tx_1', 'tx_2', 'tx_3'], $ids);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'since=tx_2'));
        Http::assertSentCount(2);
    }

    public function test_transactions_default_to_the_last_89_days(): void
    {
        CarbonImmutable::setTestNow('2024-06-01T12:00:00Z');

        Http::fake(['api.monzo.com/transactions*' => Http::response(['transactions' => []])]);

        Monzo::transactions()->list();
        Monzo::transactions()->all()->all();

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'since=2024-03-04T12%3A00%3A00Z'));
        Http::assertNotSent(fn (Request $r) => ! str_contains($r->url(), 'since='));

        CarbonImmutable::setTestNow();
    }

    public function test_attachment_upload_method_can_be_post(): void
    {
        config(['monzo.attachments.upload_method' => 'POST']);
        $this->app->forgetInstance(\Osaris\Monzo\Monzo::class);
        Monzo::clearResolvedInstances();

        Http::fake(['upload.test/*' => Http::response('', 200)]);

        Monzo::attachments()->sendToUploadUrl('https://upload.test/x', 'bytes', 'text/plain');

        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && $r->body() === 'bytes');
    }

    public function test_find_and_annotate_transaction(): void
    {
        Http::fake(['api.monzo.com/transactions/tx_1*' => Http::response(['transaction' => self::transaction('tx_1', [
            'settled' => '', 'notes' => 'Lunch', 'metadata' => ['project' => 'website'],
        ])])]);

        $tx = Monzo::transactions()->find('tx_1');
        $this->assertTrue($tx->isPending());
        $this->assertSame('Lunch', $tx->notes);
        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.monzo.com/transactions/tx_1?expand%5B%5D=merchant');

        Monzo::transactions()->annotate('tx_1', ['project' => 'website', 'old' => null]);
        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH'
            && $r->body() === 'metadata%5Bproject%5D=website&metadata%5Bold%5D=');

        Monzo::transactions()->notes('tx_1', 'Client lunch');
        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH'
            && $r->body() === 'metadata%5Bnotes%5D=Client+lunch');
    }

    public function test_feed_item(): void
    {
        Http::fake(['api.monzo.com/feed' => Http::response([])]);

        Monzo::feed()->create(
            FeedItem::make('Invoice paid', 'https://x.test/i.png')->body('£120')->url('https://x.test')->backgroundColor('FCF1EE')
        );

        Http::assertSent(function (Request $r) {
            parse_str($r->body(), $data);

            return $data === [
                'account_id' => 'acc_default',
                'type' => 'basic',
                'url' => 'https://x.test',
                'params' => [
                    'title' => 'Invoice paid',
                    'image_url' => 'https://x.test/i.png',
                    'body' => '£120',
                    'background_color' => '#FCF1EE',
                ],
            ];
        });
    }

    public function test_attachment_upload_flow(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'rcpt').'.txt';
        file_put_contents($path, 'receipt-bytes');

        Http::fake([
            'api.monzo.com/attachment/upload' => Http::response(['file_url' => 'https://files.test/r.txt', 'upload_url' => 'https://upload.test/put']),
            'upload.test/*' => Http::response('', 200),
            'api.monzo.com/attachment/register' => Http::response(['attachment' => [
                'id' => 'attach_1', 'user_id' => 'user_1', 'external_id' => 'tx_1',
                'file_url' => 'https://files.test/r.txt', 'file_type' => 'text/plain', 'created' => '2024-01-01T00:00:00Z',
            ]]),
        ]);

        $attachment = Monzo::attachments()->upload('tx_1', $path, fileType: 'text/plain');

        $this->assertSame('attach_1', $attachment->id);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/attachment/upload')
            && $r['content_length'] === '13' && $r['file_type'] === 'text/plain');
        Http::assertSent(fn (Request $r) => $r->url() === 'https://upload.test/put'
            && $r->method() === 'PUT'
            && $r->body() === 'receipt-bytes'
            && ! $r->hasHeader('Authorization'));
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/attachment/register')
            && $r['external_id'] === 'tx_1' && $r['file_url'] === 'https://files.test/r.txt');

        @unlink($path);
    }

    public function test_attachment_deregister(): void
    {
        Http::fake(['api.monzo.com/attachment/deregister' => Http::response([])]);

        Monzo::attachments()->deregister('attach_1');

        Http::assertSent(fn (Request $r) => $r['id'] === 'attach_1');
    }

    public function test_receipts(): void
    {
        Http::fake([
            'api.monzo.com/transaction-receipts?*' => Http::response(['receipt' => ['id' => 'receipt_1', 'external_id' => 'order-1']]),
            'api.monzo.com/transaction-receipts' => Http::response(['receipt_id' => 'receipt_1']),
        ]);

        $receipt = Receipt::make('tx_1', 'order-1', 1299)
            ->addItem(ReceiptItem::make('Coffee', 299)->quantity(1)->unit('cup')->tax(50))
            ->addItem(ReceiptItem::make('Meal deal', 1000)
                ->addSubItem(ReceiptItem::make('Sandwich', 600))
                ->addSubItem(ReceiptItem::make('Drink', 400)))
            ->addTax(ReceiptTax::make('VAT', 217)->taxNumber('GB123'))
            ->addPayment(ReceiptPayment::card(1299, lastFour: '4242', authCode: '123456'))
            ->merchant(ReceiptMerchant::make('Corner Café')->online(false)->storePostcode('LN1 1AA'));

        $this->assertSame(['receipt_id' => 'receipt_1'], Monzo::receipts()->create($receipt));

        Http::assertSent(fn (Request $r) => $r->method() === 'PUT'
            && $r->isJson()
            && $r['transaction_id'] === 'tx_1'
            && $r['items'][0] === ['description' => 'Coffee', 'amount' => 299, 'currency' => 'GBP', 'quantity' => 1.0, 'unit' => 'cup', 'tax' => 50]
            && count($r['items'][1]['sub_items']) === 2
            && $r['payments'][0] === ['type' => 'card', 'amount' => 1299, 'currency' => 'GBP', 'last_four' => '4242', 'auth_code' => '123456']
            && $r['merchant'] === ['name' => 'Corner Café', 'online' => false, 'store_postcode' => 'LN1 1AA']);

        $this->assertSame('receipt_1', Monzo::receipts()->find('order-1')['id']);

        Http::fake(['api.monzo.com/transaction-receipts?*' => Http::response([])]);
        Monzo::receipts()->delete('order-1');
        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), 'external_id=order-1'));
    }

    public function test_webhooks(): void
    {
        $hook = ['id' => 'webhook_1', 'account_id' => 'acc_default', 'url' => 'https://app.test/hook'];

        Http::fake(function (Request $r) use ($hook) {
            return match (true) {
                $r->method() === 'POST' => Http::response(['webhook' => $hook]),
                $r->method() === 'GET' => Http::response(['webhooks' => [$hook]]),
                $r->method() === 'DELETE' => Http::response([]),
            };
        });

        $this->assertSame('webhook_1', Monzo::webhooks()->register('https://app.test/hook')->id);
        $this->assertCount(1, Monzo::webhooks()->list());
        $this->assertSame('webhook_1', Monzo::webhooks()->ensure('https://app.test/hook')->id);
        Monzo::webhooks()->delete('webhook_1');

        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && $r->url() === 'https://api.monzo.com/webhooks/webhook_1');
        Http::assertSentCount(4); // register, list, ensure->list (no second register), delete
    }

    public function test_error_mapping(): void
    {
        Http::fake(['api.monzo.com/*' => Http::response(['code' => 'too_many_requests', 'message' => 'Slow down'], 429)]);

        try {
            Monzo::accounts()->list();
            $this->fail('Expected exception');
        } catch (RateLimitException $e) {
            $this->assertSame('Slow down', $e->getMessage());
            $this->assertSame('too_many_requests', $e->errorCode);
            $this->assertSame(429, $e->status());
        }
    }

    public function test_raw_client_escape_hatch(): void
    {
        Http::fake(['api.monzo.com/something/new*' => Http::response(['ok' => true])]);

        $this->assertSame(['ok' => true], Monzo::client()->get('/something/new', ['a' => 1]));
    }

    protected static function transaction(string $id, array $overrides = []): array
    {
        return array_merge([
            'id' => $id,
            'account_id' => 'acc_default',
            'amount' => -510,
            'currency' => 'GBP',
            'created' => '2024-01-01T10:00:00.000Z',
            'settled' => '2024-01-02T10:00:00.000Z',
            'description' => 'THE DE BEAUVOIR DELI C LONDON GBR',
            'category' => 'eating_out',
            'is_load' => false,
            'merchant' => 'merch_1',
            'metadata' => [],
            'notes' => '',
        ], $overrides);
    }
}
