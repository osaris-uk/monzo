<?php

namespace Osaris\Monzo\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

class MonzoException extends RuntimeException
{
    /**
     * @param  string|null  $errorCode  Monzo's "code" (e.g. bad_request.missing_param) or OAuth "error" (e.g. invalid_token)
     * @param  array  $body  Decoded response body, if any
     */
    public function __construct(
        string $message = '',
        int $status = 0,
        public readonly ?string $errorCode = null,
        public readonly array $body = [],
        public readonly ?Response $response = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    public static function fromResponse(Response $response): self
    {
        $status = $response->status();
        $body = $response->json();
        $body = is_array($body) ? $body : [];

        $class = match (true) {
            $status === 400 => BadRequestException::class,
            $status === 401 => AuthenticationException::class,
            $status === 403 => ForbiddenException::class,
            $status === 404 => NotFoundException::class,
            $status === 405 => MethodNotAllowedException::class,
            $status === 429 => RateLimitException::class,
            $status >= 500 => ServerException::class,
            default => self::class,
        };

        $message = $body['message']
            ?? $body['error_description']
            ?? (is_string($body['error'] ?? null) ? $body['error'] : null)
            ?? "Monzo API request failed with status {$status}.";

        $code = $body['code'] ?? (is_string($body['error'] ?? null) ? $body['error'] : null);

        return new $class($message, $status, $code, $body, $response);
    }

    public function status(): int
    {
        return $this->getCode();
    }
}
