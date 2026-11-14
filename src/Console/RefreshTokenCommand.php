<?php

namespace Osaris\Monzo\Console;

use Illuminate\Console\Command;
use Osaris\Monzo\Monzo;

/**
 * Schedule this (e.g. hourly) to keep the token warm even when the app is idle,
 * so the refresh token never goes stale.
 */
class RefreshTokenCommand extends Command
{
    protected $signature = 'monzo:refresh {--force : Refresh even if the token has not expired}';

    protected $description = 'Refresh the stored Monzo access token';

    public function handle(Monzo $monzo): int
    {
        $token = $monzo->tokens()->stored();

        if (! $token) {
            $this->error('No Monzo token stored. Run php artisan monzo:authorize first.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $token->isExpired((int) config('monzo.refresh_leeway', 60) + 3600)) {
            $this->info('Token is still valid until '.$token->expiresAt?->toDateTimeString().'. Use --force to refresh anyway.');

            return self::SUCCESS;
        }

        $fresh = $monzo->tokens()->refresh($token);

        $this->info('Token refreshed. Expires '.$fresh->expiresAt?->toDateTimeString().'.');

        return self::SUCCESS;
    }
}
