<?php

namespace Osaris\Monzo\Data;

use Carbon\CarbonImmutable;

class Attachment extends Data
{
    public readonly string $id;

    public readonly ?string $userId;

    /** The transaction ID the attachment belongs to. */
    public readonly ?string $externalId;

    public readonly string $fileUrl;

    public readonly ?string $fileType;

    public readonly ?CarbonImmutable $created;

    protected function hydrate(array $data): void
    {
        $this->id = $data['id'];
        $this->userId = $data['user_id'] ?? null;
        $this->externalId = $data['external_id'] ?? null;
        $this->fileUrl = $data['file_url'] ?? '';
        $this->fileType = $data['file_type'] ?? null;
        $this->created = static::date($data['created'] ?? null);
    }
}
