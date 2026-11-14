<?php

namespace Osaris\Monzo;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Osaris\Monzo\Auth\AccessToken;
use Osaris\Monzo\Auth\TokenManager;
use Osaris\Monzo\Exceptions\MonzoException;

/**
 * Low-level authenticated HTTP client for api.monzo.com.
 *
 * Every resource class goes through this. You can also use it directly for
 * anything the package does not wrap yet: Monzo::client()->get('/whatever').
 */
class Client
{
    protected ?AccessToken $token = null;

    public function __construct(
        protected TokenManager $tokens,
        protected array $config = [],
    ) {}

    /**
     * A copy of this client that always uses the given token (no store, no refresh).
     */
    public function withToken(AccessToken|string $token): static
    {
        $clone = clone $this;
        $clone->token = is_string($token) ? AccessToken::fromString($token) : $token;

        return $clone;
    }

    public function get(string $uri, array $query = []): array
    {
        return $this->send('GET', $uri, ['query' => $query]);
    }

    public function post(string $uri, array $form = []): array
    {
        return $this->send('POST', $uri, ['form' => $form]);
    }

    public function put(string $uri, array $form = []): array
    {
        return $this->send('PUT', $uri, ['form' => $form]);
    }

    public function patch(string $uri, array $form = []): array
    {
        return $this->send('PATCH', $uri, ['form' => $form]);
    }

    public function putJson(string $uri, array $json = []): array
    {
        return $this->send('PUT', $uri, ['json' => $json]);
    }

    public function delete(string $uri, array $query = []): array
    {
        return $this->send('DELETE', $uri, ['query' => $query]);
    }

    /**
     * @param  array{query?: array, form?: array, json?: array}  $options
     */
    public function send(string $method, string $uri, array $options = []): array
    {
        $token = $this->token ?? $this->tokens->current();

        $response = $this->dispatch($method, $uri, $options, $token);

        // Token revoked or expired early: refresh once and retry.
        if ($response->status() === 401 && $this->token === null && $this->tokens->canRefresh($token)) {
            $token = $this->tokens->refresh($token);
            $response = $this->dispatch($method, $uri, $options, $token);
        }

        if ($response->failed()) {
            throw MonzoException::fromResponse($response);
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    protected function dispatch(string $method, string $uri, array $options, AccessToken $token): Response
    {
        $url = rtrim($this->config['api_url'] ?? 'https://api.monzo.com', '/').'/'.ltrim($uri, '/');

        if (! empty($options['query']) && ($query = static::buildQuery($options['query'])) !== '') {
            $url .= (str_contains($url, '?') ? '&' : '?').$query;
        }

        $request = Http::withToken($token->accessToken, $token->tokenType)
            ->acceptJson()
            ->timeout($this->config['http']['timeout'] ?? 30)
            ->connectTimeout($this->config['http']['connect_timeout'] ?? 10);

        if (array_key_exists('json', $options)) {
            return $request->asJson()->send($method, $url, ['json' => $options['json']]);
        }

        if (array_key_exists('form', $options)) {
            return $request->asForm()->send($method, $url, ['form_params' => static::normaliseValues($options['form'])]);
        }

        return $request->send($method, $url);
    }

    /**
     * Build a query string the way Monzo expects: lists become key[]=a&key[]=b
     * (e.g. expand[]=merchant), nulls are dropped, dates become RFC 3339.
     */
    public static function buildQuery(array $query): string
    {
        $parts = [];

        foreach ($query as $key => $value) {
            if ($value === null) {
                continue;
            }

            if (is_array($value)) {
                foreach ($value as $item) {
                    $parts[] = rawurlencode((string) $key).'[]='.rawurlencode(static::scalar($item));
                }

                continue;
            }

            $parts[] = rawurlencode((string) $key).'='.rawurlencode(static::scalar($value));
        }

        return implode('&', $parts);
    }

    /**
     * Format a date the way Monzo wants it: 2009-11-10T23:00:00Z.
     */
    public static function formatDate(DateTimeInterface $date): string
    {
        return CarbonImmutable::instance($date)->utc()->format('Y-m-d\TH:i:s\Z');
    }

    protected static function normaliseValues(array $values): array
    {
        $out = [];

        foreach ($values as $key => $value) {
            if ($value === null) {
                continue;
            }

            $out[$key] = is_array($value) ? static::normaliseValues($value) : static::scalar($value);
        }

        return $out;
    }

    protected static function scalar(mixed $value): string
    {
        return match (true) {
            $value instanceof DateTimeInterface => static::formatDate($value),
            $value instanceof \BackedEnum => (string) $value->value,
            is_bool($value) => $value ? 'true' : 'false',
            default => (string) $value,
        };
    }
}
