<?php

namespace Osaris\Monzo\Data;

class Whoami extends Data
{
    public readonly bool $authenticated;

    public readonly ?string $clientId;

    public readonly ?string $userId;

    protected function hydrate(array $data): void
    {
        $this->authenticated = (bool) ($data['authenticated'] ?? false);
        $this->clientId = $data['client_id'] ?? null;
        $this->userId = $data['user_id'] ?? null;
    }
}
