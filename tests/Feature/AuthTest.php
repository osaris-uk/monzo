<?php

namespace Osaris\Monzo\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Osaris\Monzo\Contracts\TokenStore;
use Osaris\Monzo\Events\MonzoConnected;
use Osaris\Monzo\Events\TokenRefreshed;
use Osaris\Monzo\Exceptions\AuthenticationException;
use Osaris\Monzo\Exceptions\TokenNotFoundException;
use Osaris\Monzo\Facades\Monzo;
use Osaris\Monzo\Http\Controllers\OAuthController;
use Osaris\Monzo\Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_authorization_url(): void
    {
        $url = Monzo::oauth()->authorizationUrl('state123');

        $this->assertStringStartsWith('https://auth.monzo.com/?', $url);
        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame([
            'client_id' => 'oauth2client_test',
            'redirect_uri' => 'https://app.test/monzo/callback',
            'response_type' => 'code',
            'state' => 'state123',
        ], $query);
    }

    public function test_exchanging_a_code(): void
    {
        Http::fake(['api.monzo.com/oauth2/token' => Http::response(self::tokenResponse())]);

        $token = Monzo::oauth()->exchangeCode('code_abc');

        $this->assertSame('access_2', $token->accessToken);
        $this->assertSame('refresh_2', $token->refreshToken);
        $this->assertFalse($token->isExpired());

        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r['grant_type'] === 'authorization_code'
            && $r['code'] === 'code_abc'
            && $r['client_secret'] === 'secret_test'
            && $r['redirect_uri'] === 'https://app.test/monzo/callback');
    }

    public function test_missing_token_throws(): void
    {
        $this->expectException(TokenNotFoundException::class);

        Monzo::whoami();
    }

    public function test_static_access_token_is_used(): void
    {
        config(['monzo.access_token' => 'playground_token']);
        $this->app->forgetInstance(\Osaris\Monzo\Auth\TokenManager::class);
        $this->app->forgetInstance(\Osaris\Monzo\Client::class);
        $this->app->forgetInstance(\Osaris\Monzo\Monzo::class);
        Monzo::clearResolvedInstances();

        Http::fake(['api.monzo.com/ping/whoami' => Http::response(['authenticated' => true, 'client_id' => 'c', 'user_id' => 'u'])]);

        $this->assertTrue(Monzo::whoami()->authenticated);

        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer playground_token'));
    }

    public function test_expired_token_is_refreshed_before_request(): void
    {
        Event::fake([TokenRefreshed::class]);
        $this->storeToken(expiresAt: CarbonImmutable::now()->subMinute());

        Http::fake([
            'api.monzo.com/oauth2/token' => Http::response(self::tokenResponse()),
            'api.monzo.com/ping/whoami' => Http::response(['authenticated' => true]),
        ]);

        Monzo::whoami();

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/oauth2/token')
            && $r['grant_type'] === 'refresh_token'
            && $r['refresh_token'] === 'refresh_1');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/ping/whoami')
            && $r->hasHeader('Authorization', 'Bearer access_2'));

        $this->assertSame('access_2', $this->app->make(TokenStore::class)->get()->accessToken);
        Event::assertDispatched(TokenRefreshed::class);
    }

    public function test_401_triggers_one_refresh_and_retry(): void
    {
        $this->storeToken();

        Http::fake([
            'api.monzo.com/oauth2/token' => Http::response(self::tokenResponse()),
            'api.monzo.com/ping/whoami' => Http::sequence()
                ->push(['code' => 'unauthorized.bad_access_token', 'message' => 'expired'], 401)
                ->push(['authenticated' => true]),
        ]);

        $this->assertTrue(Monzo::whoami()->authenticated);
        Http::assertSentCount(3);
    }

    public function test_401_without_refresh_token_throws(): void
    {
        $this->storeToken(refresh: null);

        Http::fake(['api.monzo.com/*' => Http::response(['error' => 'invalid_token'], 401)]);

        try {
            Monzo::whoami();
            $this->fail('Expected exception');
        } catch (AuthenticationException $e) {
            $this->assertSame(401, $e->status());
            $this->assertSame('invalid_token', $e->errorCode);
        }
    }

    public function test_logout_revokes_and_forgets(): void
    {
        $this->storeToken();
        Http::fake(['api.monzo.com/oauth2/logout' => Http::response([])]);

        Monzo::logout();

        $this->assertNull($this->app->make(TokenStore::class)->get());
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && str_ends_with($r->url(), '/oauth2/logout'));
    }

    public function test_connect_route_redirects_to_monzo(): void
    {
        $response = $this->get('/monzo/connect');

        $response->assertRedirect();
        $this->assertStringStartsWith('https://auth.monzo.com/?', $response->headers->get('Location'));
        $this->assertNotEmpty(session(OAuthController::SESSION_KEY));
    }

    public function test_callback_stores_token(): void
    {
        Event::fake([MonzoConnected::class]);
        Http::fake(['api.monzo.com/oauth2/token' => Http::response(self::tokenResponse())]);

        $this->withSession([OAuthController::SESSION_KEY => 'good-state'])
            ->get('/monzo/callback?code=abc&state=good-state')
            ->assertRedirect('/');

        $this->assertSame('access_2', $this->app->make(TokenStore::class)->get()->accessToken);
        Event::assertDispatched(MonzoConnected::class);
    }

    public function test_callback_rejects_bad_state(): void
    {
        $this->withSession([OAuthController::SESSION_KEY => 'good-state'])
            ->get('/monzo/callback?code=abc&state=evil')
            ->assertForbidden();

        Http::assertNothingSent();
    }
}
