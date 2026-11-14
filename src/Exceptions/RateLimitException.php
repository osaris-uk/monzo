<?php

namespace Osaris\Monzo\Exceptions;

/**
 * 429 - too many requests. Back off and retry.
 */
class RateLimitException extends MonzoException
{
}
