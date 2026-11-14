<?php

namespace Osaris\Monzo\Data;

class Webhook extends Data
{
    public readonly string $id;

    public readonly string $accountId;

    public readonly string $url;

    protected function hydrate(array $data): void
    {
        $this->id = $data['id'];
        $this->accountId = $data['account_id'] ?? '';
        $this->url = $data['url'] ?? '';
    }
}
