<?php

namespace Osaris\Monzo\Resources;

use Illuminate\Support\Collection;
use Osaris\Monzo\Data\Webhook;

class Webhooks extends Resource
{
    /**
     * POST /webhooks
     */
    public function register(string $url, ?string $accountId = null): Webhook
    {
        $response = $this->client->post('/webhooks', [
            'account_id' => $this->accountId($accountId),
            'url' => $url,
        ]);

        return Webhook::fromArray($response['webhook']);
    }

    /**
     * GET /webhooks
     *
     * @return Collection<int, Webhook>
     */
    public function list(?string $accountId = null): Collection
    {
        $response = $this->client->get('/webhooks', [
            'account_id' => $this->accountId($accountId),
        ]);

        return Webhook::collect($response['webhooks'] ?? []);
    }

    /**
     * DELETE /webhooks/{id}
     */
    public function delete(string $webhookId): void
    {
        $this->client->delete("/webhooks/{$webhookId}");
    }

    /**
     * Register $url unless it is already registered on the account.
     */
    public function ensure(string $url, ?string $accountId = null): Webhook
    {
        return $this->list($accountId)->first(fn (Webhook $hook) => $hook->url === $url)
            ?? $this->register($url, $accountId);
    }

    /**
     * Delete every webhook on the account. Returns how many were removed.
     */
    public function deleteAll(?string $accountId = null): int
    {
        $hooks = $this->list($accountId);

        $hooks->each(fn (Webhook $hook) => $this->delete($hook->id));

        return $hooks->count();
    }
}
