<?php

namespace Osaris\Monzo\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Osaris\Monzo\Events\TransactionCreated;
use Osaris\Monzo\Events\WebhookReceived;
use Osaris\Monzo\Tests\TestCase;

class WebhookTest extends TestCase
{
    protected array $payload = [
        'type' => 'transaction.created',
        'data' => [
            'account_id' => 'acc_default',
            'amount' => -350,
            'created' => '2024-01-01T10:00:00Z',
            'currency' => 'GBP',
            'description' => 'Ozone Coffee Roasters',
            'id' => 'tx_1',
            'category' => 'eating_out',
            'is_load' => false,
            'settled' => '2024-01-02T10:00:00Z',
            'merchant' => ['id' => 'merch_1', 'name' => 'Ozone Coffee Roasters', 'category' => 'eating_out'],
        ],
    ];

    public function test_valid_webhook_dispatches_events(): void
    {
        Event::fake([WebhookReceived::class, TransactionCreated::class]);

        $this->postJson('/monzo/webhook/hook-secret', $this->payload)->assertNoContent();

        Event::assertDispatched(WebhookReceived::class, fn ($e) => $e->type === 'transaction.created');
        Event::assertDispatched(TransactionCreated::class, fn ($e) => $e->transaction->id === 'tx_1'
            && $e->transaction->merchant->name === 'Ozone Coffee Roasters'
            && $e->transaction->amount === -350);
    }

    public function test_secret_can_be_a_query_parameter(): void
    {
        Event::fake([WebhookReceived::class, TransactionCreated::class]);

        $this->postJson('/monzo/webhook?secret=hook-secret', $this->payload)->assertNoContent();

        Event::assertDispatched(TransactionCreated::class);
    }

    public function test_wrong_secret_is_rejected(): void
    {
        Event::fake([WebhookReceived::class, TransactionCreated::class]);

        $this->postJson('/monzo/webhook/nope', $this->payload)->assertForbidden();
        $this->postJson('/monzo/webhook', $this->payload)->assertForbidden();

        Event::assertNotDispatched(WebhookReceived::class);
        Event::assertNotDispatched(TransactionCreated::class);
    }

    public function test_everything_is_rejected_when_no_secret_is_configured(): void
    {
        config(['monzo.webhooks.secret' => null]);
        Event::fake([WebhookReceived::class, TransactionCreated::class]);

        $this->postJson('/monzo/webhook', $this->payload)->assertForbidden();
        $this->postJson('/monzo/webhook/anything', $this->payload)->assertForbidden();

        Event::assertNotDispatched(WebhookReceived::class);
        Event::assertNotDispatched(TransactionCreated::class);
    }

    public function test_invalid_payload(): void
    {
        $this->postJson('/monzo/webhook/hook-secret', ['nope' => true])->assertStatus(400);
    }
}
