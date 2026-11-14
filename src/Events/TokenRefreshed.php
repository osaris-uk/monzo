<?php

namespace Osaris\Monzo\Events;

use Osaris\Monzo\Auth\AccessToken;

class TokenRefreshed
{
    public function __construct(public readonly AccessToken $token) {}
}
