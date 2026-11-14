<?php

namespace Osaris\Monzo\TokenStores;

use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Osaris\Monzo\Exceptions\ConfigurationException;
use Illuminate\Support\Carbon;
use Osaris\Monzo\Auth\AccessToken;
use Osaris\Monzo\Contracts\TokenStore;
use Osaris\Monzo\Exceptions\TokenDecryptionException;
use Throwable;

/**
 * Keeps the token encrypted in a database table. The default store.
 *
 * The default because every server and container sees the same row. With a
 * store each process keeps its own copy of -- a file on a disk the web server
 * and the queue worker don't share -- the first refresh would sign every other
 * copy out, since Monzo invalidates the old refresh token each time.
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
        $payload = $this->query(fn () => $this->connection->table($this->table)->where('key', $this->key)->value('payload'));

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

        $this->query(fn () => $this->connection->table($this->table)->upsert(
            [[
                'key' => $this->key,
                'payload' => $this->encrypter->encrypt($token->toArray()),
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['key'],
            ['payload', 'updated_at'],
        ));
    }

    public function forget(): void
    {
        $this->query(fn () => $this->connection->table($this->table)->where('key', $this->key)->delete());
    }

    /**
     * Run a query, turning a missing table into the instruction that fixes it
     * rather than a bare SQL error.
     *
     * @template T
     *
     * @param  callable(): T  $query
     * @return T
     */
    protected function query(callable $query): mixed
    {
        try {
            return $query();
        } catch (QueryException $e) {
            if (! $this->connection->getSchemaBuilder()->hasTable($this->table)) {
                throw new ConfigurationException(
                    "The [{$this->table}] table does not exist. Run php artisan migrate, or set MONZO_TOKEN_STORE to another driver.",
                    previous: $e,
                );
            }

            throw $e;
        }
    }
}
