<?php

namespace Osaris\Monzo\Contracts;

use Osaris\Monzo\Auth\AccessToken;

interface TokenStore
{
    public function get(): ?AccessToken;

    public function put(AccessToken $token): void;

    public function forget(): void;
}
