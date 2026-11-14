<?php

namespace Osaris\Monzo\Console;

use Illuminate\Console\Command;
use Osaris\Monzo\Monzo;

class LogoutCommand extends Command
{
    protected $signature = 'monzo:logout';

    protected $description = 'Revoke the Monzo token and remove it from the token store';

    public function handle(Monzo $monzo): int
    {
        if (! $this->confirm('Revoke the stored Monzo token? You will need to reconnect.')) {
            return self::SUCCESS;
        }

        $monzo->logout();

        $this->info('Logged out of Monzo.');

        return self::SUCCESS;
    }
}
