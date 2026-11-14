<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One OAuth connection the app holds: a provider, and the tokens it issued.
 *
 * The tokens are encrypted with APP_KEY by the casts, so the table never
 * holds a usable token in plain text. Copy into app/Models.
 *
 * @property int $id
 * @property string $provider
 * @property string $account
 * @property string $access_token
 * @property string|null $refresh_token
 * @property string $token_type
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property string|null $provider_user_id
 * @property string|null $client_id
 */
class OAuthIntegration extends Model
{
    protected $table = 'oauth_integrations';

    protected $fillable = [
        'provider', 'account', 'access_token', 'refresh_token', 'token_type',
        'expires_at', 'provider_user_id', 'client_id',
    ];

    /** Never in an array, JSON or a log line. */
    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
        ];
    }

    /** @param  Builder<self>  $query */
    public function scopeFor(Builder $query, string $provider, string $account = 'default'): void
    {
        $query->where('provider', $provider)->where('account', $account);
    }
}
