<?php

namespace Osaris\Monzo\TokenStores;

use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Osaris\Monzo\Auth\AccessToken;
use Osaris\Monzo\Contracts\TokenStore;
use Osaris\Monzo\Exceptions\TokenDecryptionException;
use Throwable;

/**
 * Keeps the token encrypted in a database table.
 *
 * Use this when more than one machine or container talks to Monzo -- a web
 * server and a queue worker, say -- and they don't share a disk. With the
 * file store, each one would have its own copy, and because Monzo invalidates
 * the old refresh token on every refresh, the first refresh would sign every
 * other copy out.
 */
class DatabaseTokenStore implements TokenStore
{
    public function __construct(
        protected ConnectionInterface $connection,
        protected Encrypter $encrypter,
        protected string $table = 'monzo_tokens',
        protected string $key = 'default',
    ) {}

    public function get(): ?AccessToken
    {
        $payload = $this->connection->table($this->table)->where('key', $this->key)->value('payload');

        if (! is_string($payload)) {
            return null;
        }

        try {
            return AccessToken::fromArray($this->encrypter->decrypt($payload));
        } catch (Throwable $e) {
            throw new TokenDecryptionException(
                "The stored Monzo token in table [{$this->table}] could not be read. If APP_KEY has changed, reconnect (php artisan monzo:authorize) to replace it.",
                previous: $e,
            );
        }
    }

    public function put(AccessToken $token): void
    {
        $now = Carbon::now();

        $this->connection->table($this->table)->upsert(
            [[
                'key' => $this->key,
                'payload' => $this->encrypter->encrypt($token->toArray()),
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['key'],
            ['payload', 'updated_at'],
        );
    }

    public function forget(): void
    {
        $this->connection->table($this->table)->where('key', $this->key)->delete();
    }
}
