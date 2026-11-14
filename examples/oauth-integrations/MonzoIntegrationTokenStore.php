<?php

namespace App\Support\Monzo;

use App\Models\OAuthIntegration;
use Carbon\CarbonImmutable;
use Osaris\Monzo\Auth\AccessToken;
use Osaris\Monzo\Contracts\TokenStore;

/**
 * Keeps the Monzo token in the app's own oauth_integrations table, beside
 * every other provider's, instead of the package's monzo_tokens table.
 *
 * Point the package at it in config/monzo.php:
 *
 *     'token_store' => [
 *         'driver' => \App\Support\Monzo\MonzoIntegrationTokenStore::class,
 *         ...
 *     ],
 *
 * The package resolves it from the container, so the constructor can take
 * whatever it needs. Copy into app/Support/Monzo.
 */
class MonzoIntegrationTokenStore implements TokenStore
{
    public const PROVIDER = 'monzo';

    public function __construct(protected string $account = 'default') {}

    public function get(): ?AccessToken
    {
        $row = OAuthIntegration::query()->for(self::PROVIDER, $this->account)->first();

        if ($row === null) {
            return null;
        }

        return new AccessToken(
            accessToken: $row->access_token,
            refreshToken: $row->refresh_token,
            expiresAt: $row->expires_at === null ? null : CarbonImmutable::instance($row->expires_at),
            userId: $row->provider_user_id,
            clientId: $row->client_id,
            tokenType: $row->token_type,
        );
    }

    /**
     * Called after connecting and after every refresh. Monzo invalidates the
     * old refresh token each time, so this replaces the row rather than
     * adding one.
     */
    public function put(AccessToken $token): void
    {
        OAuthIntegration::query()->updateOrCreate(
            ['provider' => self::PROVIDER, 'account' => $this->account],
            [
                'access_token' => $token->accessToken,
                'refresh_token' => $token->refreshToken,
                'token_type' => $token->tokenType,
                'expires_at' => $token->expiresAt,
                'provider_user_id' => $token->userId,
                'client_id' => $token->clientId,
            ],
        );
    }

    public function forget(): void
    {
        OAuthIntegration::query()->for(self::PROVIDER, $this->account)->delete();
    }
}
