<?php

namespace Osaris\Monzo\Auth;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

final class AccessToken implements Arrayable, JsonSerializable
{
    public function __construct(
        public readonly string $accessToken,
        public readonly ?string $refreshToken = null,
        public readonly ?CarbonImmutable $expiresAt = null,
        public readonly ?string $userId = null,
        public readonly ?string $clientId = null,
        public readonly string $tokenType = 'Bearer',
    ) {}

    /**
     * Build from a /oauth2/token response.
     */
    public static function fromResponse(array $data): self
    {
        return new self(
            accessToken: $data['access_token'],
            refreshToken: $data['refresh_token'] ?? null,
            expiresAt: isset($data['expires_in'])
                ? CarbonImmutable::now()->addSeconds((int) $data['expires_in'])
                : null,
            userId: $data['user_id'] ?? null,
            clientId: $data['client_id'] ?? null,
            tokenType: $data['token_type'] ?? 'Bearer',
        );
    }

    /**
     * Build from the array produced by toArray() (what token stores persist).
     */
    public static function fromArray(array $data): self
    {
        return new self(
            accessToken: $data['access_token'],
            refreshToken: $data['refresh_token'] ?? null,
            expiresAt: ! empty($data['expires_at']) ? CarbonImmutable::parse($data['expires_at']) : null,
            userId: $data['user_id'] ?? null,
            clientId: $data['client_id'] ?? null,
            tokenType: $data['token_type'] ?? 'Bearer',
        );
    }

    /**
     * A bare token string, e.g. from the API playground. Never refreshable.
     */
    public static function fromString(string $token): self
    {
        return new self($token);
    }

    public function isExpired(int $leeway = 0): bool
    {
        return $this->expiresAt !== null
            && $this->expiresAt->subSeconds($leeway)->isPast();
    }

    public function isRefreshable(): bool
    {
        return $this->refreshToken !== null && $this->refreshToken !== '';
    }

    public function toArray(): array
    {
        return [
            'access_token' => $this->accessToken,
            'refresh_token' => $this->refreshToken,
            'expires_at' => $this->expiresAt?->toIso8601String(),
            'user_id' => $this->userId,
            'client_id' => $this->clientId,
            'token_type' => $this->tokenType,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
