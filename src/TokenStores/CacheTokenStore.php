<?php

namespace Osaris\Monzo\TokenStores;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Encryption\Encrypter;
use Osaris\Monzo\Auth\AccessToken;
use Osaris\Monzo\Contracts\TokenStore;
use Osaris\Monzo\Exceptions\TokenDecryptionException;
use Throwable;

class CacheTokenStore implements TokenStore
{
    public function __construct(
        protected Repository $cache,
        protected Encrypter $encrypter,
        protected string $key = 'monzo.token',
    ) {}

    public function get(): ?AccessToken
    {
        $payload = $this->cache->get($this->key);

        if (! is_string($payload)) {
            return null;
        }

        try {
            return AccessToken::fromArray($this->encrypter->decrypt($payload));
        } catch (Throwable $e) {
            throw new TokenDecryptionException(
                "The stored Monzo token in cache key [{$this->key}] could not be read. If APP_KEY has changed, reconnect (php artisan monzo:authorize) to replace it.",
                previous: $e,
            );
        }
    }

    public function put(AccessToken $token): void
    {
        $this->cache->forever($this->key, $this->encrypter->encrypt($token->toArray()));
    }

    public function forget(): void
    {
        $this->cache->forget($this->key);
    }
}
