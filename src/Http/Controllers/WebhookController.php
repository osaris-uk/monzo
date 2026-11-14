<?php

namespace Osaris\Monzo\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Osaris\Monzo\Data\Transaction;
use Osaris\Monzo\Events\TransactionCreated;
use Osaris\Monzo\Events\WebhookReceived;

class WebhookController
{
    public function __invoke(Request $request, ?string $secret = null): Response|JsonResponse
    {
        $expected = config('monzo.webhooks.secret');

        // Monzo doesn't sign webhooks, so the secret is the only thing stopping
        // anyone from posting fake events. With no secret set, reject everything.
        if (! is_string($expected) || $expected === '') {
            return response()->json(['message' => 'Webhook secret is not configured.'], 403);
        }

        $given = (string) ($secret ?? $request->query('secret', ''));

        if (! hash_equals($expected, $given)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $payload = $request->json()->all();
        $type = $payload['type'] ?? null;
        $data = $payload['data'] ?? [];

        if (! is_string($type) || ! is_array($data)) {
            return response()->json(['message' => 'Invalid payload.'], 400);
        }

        event(new WebhookReceived($type, $data, $payload));

        if ($type === 'transaction.created' && isset($data['id'])) {
            event(new TransactionCreated(Transaction::fromArray($data), $payload));
        }

        return response()->noContent();
    }
}
