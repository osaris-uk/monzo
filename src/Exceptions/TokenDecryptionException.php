<?php

namespace Osaris\Monzo\Exceptions;

/**
 * A stored token exists but can't be decrypted or read, usually because
 * APP_KEY has changed since it was saved. Reconnect to replace it.
 */
class TokenDecryptionException extends MonzoException
{
}
