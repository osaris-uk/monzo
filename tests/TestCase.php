<?php

namespace Osaris\Monzo\Tests;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;
use Osaris\Monzo\Auth\AccessToken;
use Osaris\Monzo\Contracts\TokenStore;
use Osaris\Monzo\MonzoServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    protected function getPackageProviders($app): array
    {
        return [MonzoServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['Monzo' => \Osaris\Monzo\Facades\Monzo::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('monzo.client_id', 'oauth2client_test');
        $app['config']->set('monzo.client_secret', 'secret_test');
        $app['config']->set('monzo.redirect_uri', 'https://app.test/monzo/callback');
        $app['config']->set('monzo.account_id', 'acc_default');
        $app['config']->set('monzo.token_store.driver', 'array');
        $app['config']->set('monzo.routes.middleware', ['web']);
        $app['config']->set('monzo.webhooks.secret', 'hook-secret');
    }

    protected function storeToken(
        string $access = 'access_1',
        ?string $refresh = 'refresh_1',
        ?CarbonImmutable $expiresAt = null,
    ): AccessToken {
        $token = new AccessToken($access, $refresh, $expiresAt ?? CarbonImmutable::now()->addHours(6), 'user_1', 'oauth2client_test');

        $this->app->make(TokenStore::class)->put($token);

        return $token;
    }

    protected static function tokenResponse(string $access = 'access_2', string $refresh = 'refresh_2'): array
    {
        return [
            'access_token' => $access,
            'client_id' => 'oauth2client_test',
            'expires_in' => 21600,
            'refresh_token' => $refresh,
            'token_type' => 'Bearer',
            'user_id' => 'user_1',
        ];
    }
}
