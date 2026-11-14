<?php

namespace Osaris\Monzo\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Osaris\Monzo\Events\MonzoConnected;
use Osaris\Monzo\Monzo;

class OAuthController
{
    public const SESSION_KEY = 'monzo.oauth_state';

    public function connect(Request $request, Monzo $monzo): RedirectResponse
    {
        $state = Str::random(40);

        $request->session()->put(self::SESSION_KEY, $state);

        return redirect()->away($monzo->oauth()->authorizationUrl($state));
    }

    public function callback(Request $request, Monzo $monzo): RedirectResponse
    {
        $expected = $request->session()->pull(self::SESSION_KEY);
        $state = (string) $request->query('state', '');

        if (! is_string($expected) || $expected === '' || ! hash_equals($expected, $state)) {
            abort(403, 'Invalid OAuth state. Start the Monzo connection again.');
        }

        $redirect = redirect(config('monzo.routes.redirect_after', '/'));

        if ($error = $request->query('error')) {
            return $redirect->with('monzo.error', (string) $request->query('error_description', $error));
        }

        $code = $request->query('code');

        if (! is_string($code) || $code === '') {
            return $redirect->with('monzo.error', 'Monzo did not return an authorization code.');
        }

        $token = $monzo->oauth()->exchangeCode($code);

        $monzo->tokens()->store($token);

        event(new MonzoConnected($token));

        return $redirect->with('monzo.connected', true);
    }
}
