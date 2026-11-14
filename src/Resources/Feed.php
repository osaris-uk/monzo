<?php

namespace Osaris\Monzo\Resources;

use Osaris\Monzo\Data\FeedItem;

class Feed extends Resource
{
    /**
     * POST /feed
     */
    public function create(FeedItem $item, ?string $accountId = null): void
    {
        $this->client->post('/feed', [
            'account_id' => $this->accountId($accountId),
            ...$item->toForm(),
        ]);
    }

    /**
     * Shortcut for a basic feed item.
     */
    public function basic(
        string $title,
        string $imageUrl,
        ?string $body = null,
        ?string $url = null,
        ?string $accountId = null,
    ): void {
        $this->create(FeedItem::make($title, $imageUrl)->body($body)->url($url), $accountId);
    }
}
