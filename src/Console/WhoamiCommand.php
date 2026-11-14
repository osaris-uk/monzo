<?php

namespace Osaris\Monzo\Console;

use Illuminate\Console\Command;
use Osaris\Monzo\Monzo;

class WhoamiCommand extends Command
{
    protected $signature = 'monzo:whoami';

    protected $description = 'Show the Monzo user and client the current token belongs to';

    public function handle(Monzo $monzo): int
    {
        $whoami = $monzo->whoami();

        $rows = [
            ['Authenticated', $whoami->authenticated ? 'yes' : 'no'],
            ['User ID', $whoami->userId],
            ['Client ID', $whoami->clientId],
        ];

        if ($token = $monzo->tokens()->stored()) {
            $rows[] = ['Token expires', $token->expiresAt?->diffForHumans() ?? 'unknown'];
            $rows[] = ['Refreshable', $token->isRefreshable() ? 'yes' : 'no'];
        }

        $this->table(['', ''], $rows);

        return self::SUCCESS;
    }
}
