<?php

namespace Osaris\Monzo\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Osaris\Monzo\Data\Webhook;
use Osaris\Monzo\Monzo;

class WebhooksCommand extends Command
{
    protected $signature = 'monzo:webhooks
                            {action=list : list, register, delete or clear}
                            {--account= : Account ID (defaults to monzo.account_id)}
                            {--url= : URL to register (defaults to this app\'s monzo.webhook route)}
                            {--id= : Webhook ID to delete}';

    protected $description = 'List, register or delete Monzo webhooks';

    public function handle(Monzo $monzo): int
    {
        $account = $this->option('account') ?: null;
        $webhooks = $monzo->webhooks();

        switch ($this->argument('action')) {
            case 'list':
                $this->table(['ID', 'Account', 'URL'], $webhooks->list($account)
                    ->map(fn (Webhook $w) => [$w->id, $w->accountId, $w->url])->all());

                return self::SUCCESS;

            case 'register':
                if (! $this->option('url') && ! config('monzo.webhooks.secret')) {
                    $this->error('Set MONZO_WEBHOOK_SECRET first. The package route rejects every request until a secret is set.');

                    return self::FAILURE;
                }

                $url = $this->option('url') ?: $this->defaultUrl();

                if (! $url) {
                    $this->error('Pass --url, or enable the package webhook route.');

                    return self::FAILURE;
                }

                $hook = $webhooks->ensure($url, $account);
                $this->info("Webhook {$hook->id} registered for {$hook->url}");

                return self::SUCCESS;

            case 'delete':
                if (! $id = $this->option('id')) {
                    $this->error('Pass --id=webhook_...');

                    return self::FAILURE;
                }

                $webhooks->delete($id);
                $this->info("Deleted {$id}.");

                return self::SUCCESS;

            case 'clear':
                if (! $this->confirm('Delete every webhook on this account?')) {
                    return self::SUCCESS;
                }

                $this->info('Deleted '.$webhooks->deleteAll($account).' webhook(s).');

                return self::SUCCESS;
        }

        $this->error('Unknown action. Use list, register, delete or clear.');

        return self::INVALID;
    }

    protected function defaultUrl(): ?string
    {
        if (! Route::has('monzo.webhook')) {
            return null;
        }

        return route('monzo.webhook', ['secret' => config('monzo.webhooks.secret')]);
    }
}
