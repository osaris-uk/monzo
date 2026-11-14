# Security policy

This package handles OAuth tokens for real bank accounts, so security reports are taken seriously.

## Reporting a vulnerability

**Please don't open a public issue.** Report it privately through GitHub's [private vulnerability reporting](https://github.com/osaris-uk/monzo/security/advisories/new) (the **Security** tab → **Report a vulnerability**).

Please include:

- what the issue is and what an attacker could do with it
- steps or code to reproduce it
- the package, PHP and Laravel versions you tested on

You'll get an acknowledgement as soon as possible. Once a fix is released, the advisory is published with credit to you, unless you'd rather stay anonymous.

## Supported versions

Only the latest release gets security fixes.

## Scope notes

- Monzo doesn't sign webhooks. The package protects its webhook route with the `MONZO_WEBHOOK_SECRET` in the URL and rejects every request until that's set. Treat the secret like a password.
- Tokens are encrypted with your app's `APP_KEY` before they're stored. Anyone with `APP_KEY` and access to the token store can use your Monzo connection.
