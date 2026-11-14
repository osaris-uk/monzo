<?php

namespace Osaris\Monzo\Tests\Unit;

use Carbon\CarbonImmutable;
use Illuminate\Encryption\Encrypter;
use Illuminate\Filesystem\Filesystem;
use Osaris\Monzo\Auth\AccessToken;
use Osaris\Monzo\Exceptions\TokenDecryptionException;
use Osaris\Monzo\TokenStores\CacheTokenStore;
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

    public function test_token_expiry_with_leeway(): void
    {
        $token = new AccessToken('a', expiresAt: CarbonImmutable::now()->addSeconds(30));

        $this->assertFalse($token->isExpired());
        $this->assertTrue($token->isExpired(60));
        $this->assertFalse(AccessToken::fromString('x')->isExpired());
        $this->assertFalse(AccessToken::fromString('x')->isRefreshable());
    }
}
