<?php

namespace Osaris\Monzo\Console;

use Illuminate\Console\Command;
use Osaris\Monzo\Data\Account;
use Osaris\Monzo\Monzo;

class AccountsCommand extends Command
{
    protected $signature = 'monzo:accounts
                            {--type= : Filter by account type (uk_retail, uk_retail_joint, uk_business)}
                            {--balances : Include each account\'s balance}
                            {--closed : Include closed accounts}';

    protected $description = 'List the Monzo accounts the current token can see';

    public function handle(Monzo $monzo): int
    {
        $accounts = $monzo->accounts()->list();

        if ($type = $this->option('type')) {
            $accounts = $accounts->filter(fn (Account $a) => $a->type === $type);
        }

        if (! $this->option('closed')) {
            $accounts = $accounts->reject(fn (Account $a) => $a->closed);
        }

        $headers = ['ID', 'Type', 'Description', 'Sort code', 'Account no.', 'Created'];

        if ($this->option('balances')) {
            $headers[] = 'Balance';
        }

        $rows = $accounts->map(function (Account $account) use ($monzo) {
            $row = [
                $account->id,
                $account->type,
                $account->description,
                $account->sortCode,
                $account->accountNumber,
                $account->created?->toDateString(),
            ];

            if ($this->option('balances')) {
                $balance = $monzo->balance($account->id);
                $row[] = number_format($balance->amount(), 2).' '.$balance->currency;
            }

            return $row;
        })->values()->all();

        $this->table($headers, $rows);

        return self::SUCCESS;
    }
}
