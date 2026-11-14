<?php

namespace Osaris\Monzo\Exceptions;

/**
 * 403 - authenticated but without permission. Also returned until the user approves access in the Monzo app.
 */
class ForbiddenException extends MonzoException
{
}
