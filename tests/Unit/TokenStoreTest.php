<?php

namespace Osaris\Monzo\Tests\Unit;

use Carbon\CarbonImmutable;
use Illuminate\Encryption\Encrypter;
use Illuminate\Filesystem\Filesystem;
use Osaris\Monzo\Auth\AccessToken;
use Osaris\Monzo\Exceptions\ConfigurationException;
use Osaris\Monzo\Exceptions\TokenDecryptionException;
use Illuminate\Support\ServiceProvider;
use Osaris\Monzo\Contracts\TokenStore;
use Osaris\Monzo\MonzoServiceProvider;
use Osaris\Monzo\TokenStores\CacheTokenStore;
use Osaris\Monzo\TokenStores\DatabaseTokenStore;
use Osaris\Monzo\TokenStores\FileTokenStore;
use Osaris\Monzo\Tests\TestCase;

class TokenStoreTest extends TestCase
{
    public function test_file_store_round_trips_encrypted(): void
    {
        $path = sys_get_temp_dir().'/monzo-test-'.uniqid().'/token.json';
        $store = new FileTokenStore(new Filesystem, $this->app['encrypter'], $path);
        $token = new AccessToken('a', 'r', CarbonImmutable::parse('2030-01-01T00:00:00Z'), 'user_1');

        $this->assertNull($store->get());

        $store->put($token);

        $this->assertStringNotContainsString('"a"', file_get_contents($path));
        $this->assertEquals($token->toArray(), $store->get()->toArray());

        $store->forget();
        $this->assertNull($store->get());
    }

    public function test_cache_store_round_trips(): void
    {
        $store = new CacheTokenStore($this->app['cache']->store('array'), $this->app['encrypter']);

        $store->put(new AccessToken('a', 'r'));

        $this->assertSame('a', $store->get()->accessToken);

        $store->forget();
        $this->assertNull($store->get());
    }

    public function test_undecryptable_file_token_throws_a_clear_error(): void
    {
        $path = sys_get_temp_dir().'/monzo-test-'.uniqid().'/token.json';
        $store = new FileTokenStore(new Filesystem, $this->app['encrypter'], $path);
        $store->put(new AccessToken('a', 'r'));

        // Same token file, different APP_KEY.
        $other = new FileTokenStore(new Filesystem, new Encrypter(str_repeat('b', 32), 'AES-256-CBC'), $path);

        try {
            $other->get();
            $this->fail('Expected TokenDecryptionException');
        } catch (TokenDecryptionException $e) {
            $this->assertStringContainsString('APP_KEY', $e->getMessage());
        } finally {
            $store->forget();
        }
    }

    public function test_undecryptable_cache_token_throws_a_clear_error(): void
    {
        $cache = $this->app['cache']->store('array');
        $cache->forever('monzo.token', 'not-a-valid-payload');

        $this->expectException(TokenDecryptionException::class);

        (new CacheTokenStore($cache, $this->app['encrypter']))->get();
    }

    public function test_database_store_round_trips_encrypted_and_keeps_one_row(): void
    {
        $this->createTokensTable();
        $store = new DatabaseTokenStore($this->app['db']->connection(), $this->app['encrypter']);

        $this->assertNull($store->get());

        $store->put(new AccessToken('a', 'r', CarbonImmutable::parse('2030-01-01T00:00:00Z'), 'user_1'));
        $store->put(new AccessToken('b', 's', CarbonImmutable::parse('2030-01-01T00:00:00Z'), 'user_1'));

        $rows = $this->app['db']->table('monzo_tokens')->get();

        // A refresh replaces the token rather than adding a second one.
        $this->assertCount(1, $rows);
        $this->assertStringNotContainsString('"b"', $rows[0]->payload);
        $this->assertSame('b', $store->get()->accessToken);
        $this->assertSame('s', $store->get()->refreshToken);

        $store->forget();
        $this->assertNull($store->get());
    }

    public function test_database_stores_with_different_keys_do_not_share_a_token(): void
    {
        $this->createTokensTable();
        $first = new DatabaseTokenStore($this->app['db']->connection(), $this->app['encrypter'], key: 'business');
        $second = new DatabaseTokenStore($this->app['db']->connection(), $this->app['encrypter'], key: 'personal');

        $first->put(new AccessToken('a', 'r'));

        $this->assertSame('a', $first->get()->accessToken);
        $this->assertNull($second->get());
    }

    public function test_undecryptable_database_token_throws_a_clear_error(): void
    {
        $this->createTokensTable();
        $this->app['db']->table('monzo_tokens')->insert(['key' => 'default', 'payload' => 'not-a-valid-payload']);

        $this->expectException(TokenDecryptionException::class);
        $this->expectExceptionMessage('APP_KEY');

        (new DatabaseTokenStore($this->app['db']->connection(), $this->app['encrypter']))->get();
    }

    public function test_the_database_driver_is_chosen_from_config(): void
    {
        $this->app['config']->set('monzo.token_store.driver', 'database');
        $this->app->forgetInstance(TokenStore::class);

        $this->assertInstanceOf(DatabaseTokenStore::class, $this->app->make(TokenStore::class));
    }

    public function test_the_migration_can_be_published(): void
    {
        $paths = ServiceProvider::pathsToPublish(MonzoServiceProvider::class, 'monzo-migrations');

        $this->assertCount(1, $paths);
        $this->assertFileExists(array_key_first($paths).'/2026_11_14_000000_create_monzo_tokens_table.php');
    }

    public function test_the_database_is_the_default_store(): void
    {
        $config = require __DIR__.'/../../config/monzo.php';

        $this->assertSame('database', $config['token_store']['driver']);
    }

    public function test_a_missing_table_says_how_to_fix_it(): void
    {
        $store = new DatabaseTokenStore($this->app['db']->connection(), $this->app['encrypter']);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Run php artisan migrate');

        $store->get();
    }

    protected function createTokensTable(): void
    {
        (require __DIR__.'/../../database/migrations/2026_11_14_000000_create_monzo_tokens_table.php')->up();
    }

    public function test_token_expiry_with_leeway(): void
    {
        $token = new AccessToken('a', expiresAt: CarbonImmutable::now()->addSeconds(30));

        $this->assertFalse($token->isExpired());
        $this->assertTrue($token->isExpired(60));
        $this->assertFalse(AccessToken::fromString('x')->isExpired());
        $this->assertFalse(AccessToken::fromString('x')->isRefreshable());
    }
}
