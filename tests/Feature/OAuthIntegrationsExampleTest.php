<?php

namespace Osaris\Monzo\Tests\Feature;

use App\Models\OAuthIntegration;
use App\Support\Monzo\MonzoIntegrationTokenStore;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Osaris\Monzo\Auth\AccessToken;
use Osaris\Monzo\Contracts\TokenStore;
use Osaris\Monzo\Exceptions\ConfigurationException;
use Osaris\Monzo\Facades\Monzo;
use Osaris\Monzo\Tests\TestCase;

require_once __DIR__.'/../../examples/oauth-integrations/OAuthIntegration.php';
require_once __DIR__.'/../../examples/oauth-integrations/MonzoIntegrationTokenStore.php';

/**
 * The README's example of keeping the token in an app's own OAuth
 * integrations table, run as written -- so the example cannot drift from
 * what the package actually calls.
 */
class OAuthIntegrationsExampleTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('monzo.token_store.driver', MonzoIntegrationTokenStore::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        (require __DIR__.'/../../examples/oauth-integrations/2026_11_14_000000_create_oauth_integrations_table.php')->up();
    }

    public function test_the_package_uses_the_apps_store(): void
    {
        $this->assertInstanceOf(MonzoIntegrationTokenStore::class, $this->app->make(TokenStore::class));
    }

    public function test_a_token_round_trips_through_the_table_encrypted(): void
    {
        $store = $this->app->make(TokenStore::class);
        $store->put(new AccessToken('access_1', 'refresh_1', CarbonImmutable::parse('2030-01-01 00:00:00'), 'user_1', 'oauth2client_test'));

        $row = $this->app['db']->table('oauth_integrations')->first();

        $this->assertSame('monzo', $row->provider);
        $this->assertSame('default', $row->account);
        $this->assertStringNotContainsString('access_1', $row->access_token);
        $this->assertStringNotContainsString('refresh_1', $row->refresh_token);

        $token = $store->get();

        $this->assertSame('access_1', $token->accessToken);
        $this->assertSame('refresh_1', $token->refreshToken);
        $this->assertSame('2030-01-01', $token->expiresAt->toDateString());
        $this->assertSame('user_1', $token->userId);

        $store->forget();
        $this->assertNull($store->get());
    }

    public function test_other_providers_rows_are_left_alone(): void
    {
        OAuthIntegration::query()->create(['provider' => 'hmrc', 'access_token' => 'hmrc_token']);

        $store = $this->app->make(TokenStore::class);
        $store->put(new AccessToken('access_1', 'refresh_1'));
        $store->forget();

        $this->assertSame(['hmrc'], OAuthIntegration::query()->pluck('provider')->all());
    }

    public function test_a_refresh_replaces_the_row_with_the_new_token(): void
    {
        $this->storeToken(expiresAt: CarbonImmutable::now()->subMinute());

        Http::fake([
            'api.monzo.com/oauth2/token' => Http::response(self::tokenResponse('access_2', 'refresh_2')),
            'api.monzo.com/ping/whoami' => Http::response(['authenticated' => true]),
        ]);

        Monzo::whoami();

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/oauth2/token') && $r['refresh_token'] === 'refresh_1');

        $this->assertSame(1, OAuthIntegration::query()->count());
        $this->assertSame('refresh_2', OAuthIntegration::query()->sole()->refresh_token);
    }

    public function test_the_tokens_never_appear_when_the_row_is_serialised(): void
    {
        $this->storeToken();

        $this->assertArrayNotHasKey('access_token', OAuthIntegration::query()->sole()->toArray());
    }

    public function test_a_class_that_is_not_a_token_store_is_refused_clearly(): void
    {
        $this->useDriver(\stdClass::class);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('must implement');

        $this->app->make(TokenStore::class);
    }

    public function test_a_class_that_does_not_exist_is_named(): void
    {
        $this->useDriver('App\\Missing\\Store');

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Unknown Monzo token store [App\\Missing\\Store]');

        $this->app->make(TokenStore::class);
    }

    /** The store is resolved when first asked for, so the driver can change after boot. */
    protected function useDriver(string $driver): void
    {
        $this->app['config']->set('monzo.token_store.driver', $driver);
        $this->app->forgetInstance(TokenStore::class);
    }
}
