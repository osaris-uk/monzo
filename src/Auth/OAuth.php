<?php

namespace Osaris\Monzo\Auth;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Osaris\Monzo\Exceptions\ConfigurationException;
use Osaris\Monzo\Exceptions\MonzoException;

class OAuth
{
    public function __construct(protected array $config) {}

    /**
     * The URL to send the user to so they can approve access.
     *
     * Pass a random $state and check it on the way back (the package's
     * callback route does this for you).
     */
    public function authorizationUrl(?string $state = null): string
    {
        $query = array_filter([
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'state' => $state,
        ], fn ($value) => $value !== null);

        return rtrim($this->config['auth_url'] ?? 'https://auth.monzo.com', '/').'/?'.http_build_query($query);
    }

    /**
     * Exchange the authorization code from the callback for a token.
     */
    public function exchangeCode(string $code): AccessToken
    {
        return $this->requestToken([
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'redirect_uri' => $this->redirectUri(),
            'code' => $code,
        ]);
    }

    /**
     * Get a new token pair. Monzo invalidates the old refresh token.
     */
    public function refresh(string $refreshToken): AccessToken
    {
        return $this->requestToken([
            'grant_type' => 'refresh_token',
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'refresh_token' => $refreshToken,
        ]);
    }

    public function redirectUri(): string
    {
        if (! empty($this->config['redirect_uri'])) {
            return $this->config['redirect_uri'];
        }

        if (Route::has('monzo.callback')) {
            return route('monzo.callback');
        }

        throw new ConfigurationException('Set monzo.redirect_uri (MONZO_REDIRECT_URI) or enable the package routes.');
    }

    protected function requestToken(array $params): AccessToken
    {
        $response = Http::asForm()
            ->acceptJson()
            ->timeout($this->config['http']['timeout'] ?? 30)
            ->post($this->apiUrl('/oauth2/token'), $params);

        if ($response->failed()) {
            throw MonzoException::fromResponse($response);
        }

        return AccessToken::fromResponse($response->json());
    }

    protected function apiUrl(string $path): string
    {
        return rtrim($this->config['api_url'] ?? 'https://api.monzo.com', '/').$path;
    }

    protected function clientId(): string
    {
        return $this->config['client_id']
            ?: throw new ConfigurationException('Set monzo.client_id (MONZO_CLIENT_ID).');
    }

    protected function clientSecret(): string
    {
        return $this->config['client_secret']
            ?: throw new ConfigurationException('Set monzo.client_secret (MONZO_CLIENT_SECRET).');
    }
}
