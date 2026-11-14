<?php

namespace Osaris\Monzo\Events;


/**
 * Fired for every valid webhook Monzo sends, whatever its type.
 */
class WebhookReceived
{
    public function __construct(
        public readonly string $type,
        public readonly array $data,
        public readonly array $payload,
    ) {}
}
