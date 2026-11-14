<?php

namespace Osaris\Monzo\TokenStores;

use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Filesystem\Filesystem;
use Osaris\Monzo\Auth\AccessToken;
use Osaris\Monzo\Contracts\TokenStore;
use Osaris\Monzo\Exceptions\TokenDecryptionException;
use Throwable;

class FileTokenStore implements TokenStore
{
    public function __construct(
        protected Filesystem $files,
        protected Encrypter $encrypter,
        protected string $path,
    ) {}

    public function get(): ?AccessToken
    {
        if (! $this->files->exists($this->path)) {
            return null;
        }

        try {
            return AccessToken::fromArray(
                $this->encrypter->decrypt($this->files->get($this->path, lock: true))
            );
        } catch (Throwable $e) {
            throw new TokenDecryptionException(
                "The stored Monzo token at [{$this->path}] could not be read. If APP_KEY has changed, reconnect (php artisan monzo:authorize) to replace it.",
                previous: $e,
            );
        }
    }

    public function put(AccessToken $token): void
    {
        $this->files->ensureDirectoryExists(dirname($this->path), 0700);

        $this->files->put($this->path, $this->encrypter->encrypt($token->toArray()), lock: true);

        @chmod($this->path, 0600);
    }

    public function forget(): void
    {
        $this->files->delete($this->path);
    }
}
