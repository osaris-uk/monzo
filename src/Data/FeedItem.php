<?php

namespace Osaris\Monzo\Data;

use InvalidArgumentException;

/**
 * Builder for a "basic" feed item (the only type Monzo supports).
 *
 *   FeedItem::make('Invoice paid', 'https://example.com/icon.png')
 *       ->body('Acme Ltd paid £120.00')
 *       ->url('https://example.com/invoices/42')
 *       ->backgroundColor('#FCF1EE');
 */
class FeedItem
{
    protected ?string $body = null;

    protected ?string $url = null;

    protected ?string $backgroundColor = null;

    protected ?string $titleColor = null;

    protected ?string $bodyColor = null;

    public function __construct(
        protected string $title,
        protected string $imageUrl,
    ) {}

    public static function make(string $title, string $imageUrl): static
    {
        return new static($title, $imageUrl);
    }

    public function body(?string $body): static
    {
        $this->body = $body;

        return $this;
    }

    /** Opened when the user taps the item. */
    public function url(?string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function backgroundColor(?string $hex): static
    {
        $this->backgroundColor = static::hex($hex);

        return $this;
    }

    public function titleColor(?string $hex): static
    {
        $this->titleColor = static::hex($hex);

        return $this;
    }

    public function bodyColor(?string $hex): static
    {
        $this->bodyColor = static::hex($hex);

        return $this;
    }

    /**
     * Form fields for POST /feed (without account_id).
     */
    public function toForm(): array
    {
        return array_filter([
            'type' => 'basic',
            'url' => $this->url,
            'params' => array_filter([
                'title' => $this->title,
                'image_url' => $this->imageUrl,
                'body' => $this->body,
                'background_color' => $this->backgroundColor,
                'title_color' => $this->titleColor,
                'body_color' => $this->bodyColor,
            ], fn ($value) => $value !== null),
        ], fn ($value) => $value !== null);
    }

    protected static function hex(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = '#'.ltrim($value, '#');

        if (! preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
            throw new InvalidArgumentException("Feed item colours must be #RRGGBB, got [{$value}].");
        }

        return $value;
    }
}
