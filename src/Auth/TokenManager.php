<?php

namespace Osaris\Monzo\Auth;

use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository as Cache;
use Osaris\Monzo\Contracts\TokenStore;
use Osaris\Monzo\Events\TokenRefreshed;
use Osaris\Monzo\Exceptions\MonzoException;
use Osaris\Monzo\Exceptions\TokenNotFoundException;

class TokenManager
{
    public function __construct(
        protected TokenStore $store,
        protected OAuth $oauth,
        protected array $config = [],
        protected ?Cache $cache = null,
    ) {}

    /**
     * The token to use for the next request, refreshed first if it has expired.
     */
    public function current(): AccessToken
    {
        if ($static = $this->staticToken()) {
            return $static;
        }

        $token = $this->store->get()
            ?? throw new TokenNotFoundException('No Monzo token stored. Connect an account first (visit the monzo.connect route or run php artisan monzo:authorize).');

        if ($this->autoRefresh() && $token->isRefreshable() && $token->isExpired($this->leeway())) {
            $token = $this->refresh($token);
        }

        return $token;
    }

    public function canRefresh(?AccessToken $token = null): bool
    {
        if ($this->staticToken() || ! $this->autoRefresh()) {
            return false;
        }

        $token ??= $this->store->get();

        return $token?->isRefreshable() ?? false;
    }

    /**
     * Refresh the stored token (or the one given) and persist the result.
     */
    public function refresh(?AccessToken $token = null): AccessToken
    {
        $token ??= $this->store->get()
            ?? throw new TokenNotFoundException('No Monzo token stored to refresh.');

        return $this->withLock(function () use ($token) {
            // Another process may have refreshed while we waited for the lock.
            $stored = $this->store->get();

            if ($stored && $stored->accessToken !== $token->accessToken && ! $stored->isExpired($this->leeway())) {
                return $stored;
            }

            $current = $stored ?? $token;

            if (! $current->isRefreshable()) {
                throw new MonzoException('The Monzo token has no refresh token. Use a confidential OAuth client to get one.');
            }

            $fresh = $this->oauth->refresh($current->refreshToken);

            $this->store->put($fresh);

            event(new TokenRefreshed($fresh));

            return $fresh;
        });
    }

    public function store(AccessToken $token): void
    {
        $this->store->put($token);
    }

    public function stored(): ?AccessToken
    {
        return $this->store->get();
    }

    public function forget(): void
    {
        $this->store->forget();
    }

    public function hasToken(): bool
    {
        return $this->staticToken() !== null || $this->store->get() !== null;
    }

    protected function staticToken(): ?AccessToken
    {
        $token = $this->config['access_token'] ?? null;

        return is_string($token) && $token !== '' ? AccessToken::fromString($token) : null;
    }

    protected function autoRefresh(): bool
    {
        return filter_var($this->config['auto_refresh'] ?? true, FILTER_VALIDATE_BOOL);
    }

    protected function leeway(): int
    {
        return (int) ($this->config['refresh_leeway'] ?? 60);
    }

    protected function withLock(callable $callback): mixed
    {
        $store = $this->cache?->getStore();

        if ($store instanceof LockProvider) {
            return $store->lock('monzo:token-refresh', 30)->block(15, $callback);
        }

        return $callback();
    }
}
