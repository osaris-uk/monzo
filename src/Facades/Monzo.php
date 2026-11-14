<?php

namespace Osaris\Monzo\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Osaris\Monzo\Monzo withToken(\Osaris\Monzo\Auth\AccessToken|string $token)
 * @method static \Osaris\Monzo\Monzo forAccount(string $accountId)
 * @method static \Osaris\Monzo\Auth\OAuth oauth()
 * @method static \Osaris\Monzo\Auth\TokenManager tokens()
 * @method static \Osaris\Monzo\Client client()
 * @method static \Osaris\Monzo\Data\Whoami whoami()
 * @method static void logout()
 * @method static \Osaris\Monzo\Resources\Accounts accounts()
 * @method static \Osaris\Monzo\Resources\Balances balances()
 * @method static \Osaris\Monzo\Data\Balance balance(?string $accountId = null)
 * @method static \Osaris\Monzo\Resources\Pots pots()
 * @method static \Osaris\Monzo\Resources\Transactions transactions()
 * @method static \Osaris\Monzo\Resources\Feed feed()
 * @method static \Osaris\Monzo\Resources\Attachments attachments()
 * @method static \Osaris\Monzo\Resources\Receipts receipts()
 * @method static \Osaris\Monzo\Resources\Webhooks webhooks()
 * @method static string|null defaultAccountId()
 *
 * @see \Osaris\Monzo\Monzo
 */
class Monzo extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Osaris\Monzo\Monzo::class;
    }
}
