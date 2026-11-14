<?php

namespace Osaris\Monzo\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Osaris\Monzo\Events\MonzoConnected;
use Osaris\Monzo\Exceptions\InvalidStateException;
use Osaris\Monzo\Monzo;

/**
 * Connect a Monzo account from the terminal, without the web routes.
 */
class AuthorizeCommand extends Command
{
    protected $signature = 'monzo:authorize';

    protected $description = 'Connect a Monzo account via OAuth from the command line';

    public function handle(Monzo $monzo): int
    {
        $state = Str::random(40);

        $this->line('1. Open this URL and sign in with the Monzo email link:');
        $this->newLine();
        $url = $monzo->oauth()->authorizationUrl($state);
        $this->line("   <href={$url}>{$url}</>");
        $this->newLine();
        $this->line('2. After the redirect, copy the full URL from your browser (or just the code).');

        $answer = trim((string) $this->ask('Paste it here'));

        [$code, $returnedState] = $this->parse($answer);

        if ($returnedState !== null && ! hash_equals($state, $returnedState)) {
            throw new InvalidStateException('The state in that URL does not match. Run the command again.');
        }

        if ($code === null) {
            $this->error('No authorization code found.');

            return self::FAILURE;
        }

        $token = $monzo->oauth()->exchangeCode($code);
        $monzo->tokens()->store($token);

        event(new MonzoConnected($token));

        $this->info("Connected as {$token->userId}.");
        $this->warn('Now approve access in the Monzo app, or most API calls will return 403.');

        if (! $token->isRefreshable()) {
            $this->warn('No refresh token was issued (non-confidential client). You will need to reconnect when it expires.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    protected function parse(string $answer): array
    {
        if (! str_contains($answer, '?') && ! str_contains($answer, '=')) {
            return [$answer !== '' ? $answer : null, null];
        }

        parse_str((string) parse_url($answer, PHP_URL_QUERY) ?: ltrim($answer, '?'), $query);

        return [$query['code'] ?? null, $query['state'] ?? null];
    }
}
