<?php

namespace Osaris\Monzo\TokenStores;

use Osaris\Monzo\Auth\AccessToken;
use Osaris\Monzo\Contracts\TokenStore;

class ArrayTokenStore implements TokenStore
{
    public function __construct(protected ?AccessToken $token = null) {}

    public function get(): ?AccessToken
    {
        return $this->token;
    }

    public function put(AccessToken $token): void
    {
        $this->token = $token;
    }

    public function forget(): void
    {
        $this->token = null;
    }
}
