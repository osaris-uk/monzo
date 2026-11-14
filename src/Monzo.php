<?php

namespace Osaris\Monzo;

use Osaris\Monzo\Auth\AccessToken;
use Osaris\Monzo\Auth\OAuth;
use Osaris\Monzo\Auth\TokenManager;
use Osaris\Monzo\Data\Balance;
use Osaris\Monzo\Data\Whoami;
use Osaris\Monzo\Resources\Accounts;
use Osaris\Monzo\Resources\Attachments;
use Osaris\Monzo\Resources\Balances;
use Osaris\Monzo\Resources\Feed;
use Osaris\Monzo\Resources\Pots;
use Osaris\Monzo\Resources\Receipts;
use Osaris\Monzo\Resources\Transactions;
use Osaris\Monzo\Resources\Webhooks;

class Monzo
{
    public function __construct(
        protected Client $client,
        protected OAuth $oauth,
        protected TokenManager $tokens,
        protected array $config = [],
    ) {}

    /**
     * Use a specific token for the calls on the returned instance.
     * The token store is not touched and the token is never refreshed.
     */
    public function withToken(AccessToken|string $token): static
    {
        $clone = clone $this;
        $clone->client = $this->client->withToken($token);

        return $clone;
    }

    /**
     * Use a different default account for the calls on the returned instance.
     */
    public function forAccount(string $accountId): static
    {
        $clone = clone $this;
        $clone->config['account_id'] = $accountId;

        return $clone;
    }

    public function oauth(): OAuth
    {
        return $this->oauth;
    }

    public function tokens(): TokenManager
    {
        return $this->tokens;
    }

    public function client(): Client
    {
        return $this->client;
    }

    /**
     * GET /ping/whoami
     */
    public function whoami(): Whoami
    {
        return Whoami::fromArray($this->client->get('/ping/whoami'));
    }

    /**
     * POST /oauth2/logout — revoke the token and remove it from the store.
     */
    public function logout(): void
    {
        $this->client->post('/oauth2/logout');

        $this->tokens->forget();
    }

    public function accounts(): Accounts
    {
        return new Accounts($this->client, $this->defaultAccountId());
    }

    public function balances(): Balances
    {
        return new Balances($this->client, $this->defaultAccountId());
    }

    /**
     * Shortcut for balances()->get().
     */
    public function balance(?string $accountId = null): Balance
    {
        return $this->balances()->get($accountId);
    }

    public function pots(): Pots
    {
        return new Pots($this->client, $this->defaultAccountId());
    }

    public function transactions(): Transactions
    {
        return new Transactions($this->client, $this->defaultAccountId());
    }

    public function feed(): Feed
    {
        return new Feed($this->client, $this->defaultAccountId());
    }

    public function attachments(): Attachments
    {
        return new Attachments(
            $this->client,
            $this->defaultAccountId(),
            $this->config['attachments']['upload_method'] ?? 'PUT',
        );
    }

    public function receipts(): Receipts
    {
        return new Receipts($this->client, $this->defaultAccountId());
    }

    public function webhooks(): Webhooks
    {
        return new Webhooks($this->client, $this->defaultAccountId());
    }

    public function defaultAccountId(): ?string
    {
        $id = $this->config['account_id'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }
}
