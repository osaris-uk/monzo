<?php

namespace Osaris\Monzo\Tests\Feature;

use Osaris\Monzo\Tests\TestCase;
use Osaris\Monzo\TokenStores\ArrayTokenStore;

/**
 * Which migrations the package runs depends on the driver chosen before it
 * boots, so each case names its own config, applied after the base test
 * case's.
 */
class MigrationsTest extends TestCase
{
    /** @var array<string, array<string, mixed>> */
    protected const CONFIG = [
        'test_the_database_store_needs_only_migrate' => [
            'monzo.token_store.driver' => 'database',
        ],
        'test_an_app_that_publishes_the_migration_can_turn_off_the_packages_own' => [
            'monzo.token_store.driver' => 'database',
            'monzo.token_store.migrations' => false,
        ],
        'test_an_app_with_its_own_store_gets_no_monzo_tokens_table' => [
            'monzo.token_store.driver' => ArrayTokenStore::class,
        ],
    ];

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        foreach (self::CONFIG[$this->name()] ?? [] as $key => $value) {
            $app['config']->set($key, $value);
        }
    }

    public function test_the_database_store_needs_only_migrate(): void
    {
        $this->artisan('migrate')->assertSuccessful();

        $this->assertTrue($this->hasTokensTable());
    }

    public function test_an_app_that_publishes_the_migration_can_turn_off_the_packages_own(): void
    {
        $this->artisan('migrate')->assertSuccessful();

        $this->assertFalse($this->hasTokensTable());
    }

    public function test_an_app_with_its_own_store_gets_no_monzo_tokens_table(): void
    {
        $this->artisan('migrate')->assertSuccessful();

        $this->assertFalse($this->hasTokensTable());
    }

    protected function hasTokensTable(): bool
    {
        return $this->app['db']->connection()->getSchemaBuilder()->hasTable('monzo_tokens');
    }
}
