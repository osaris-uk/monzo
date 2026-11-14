<?php

namespace Osaris\Monzo;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Osaris\Monzo\Auth\OAuth;
use Osaris\Monzo\Auth\TokenManager;
use Osaris\Monzo\Contracts\TokenStore;
use Osaris\Monzo\Exceptions\ConfigurationException;
use Osaris\Monzo\Http\Controllers\OAuthController;
use Osaris\Monzo\Http\Controllers\WebhookController;
use Osaris\Monzo\TokenStores\ArrayTokenStore;
use Osaris\Monzo\TokenStores\CacheTokenStore;
use Osaris\Monzo\TokenStores\DatabaseTokenStore;
use Osaris\Monzo\TokenStores\FileTokenStore;

class MonzoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/monzo.php', 'monzo');

        $this->app->singleton(TokenStore::class, fn (Application $app) => $this->makeTokenStore($app));

        $this->app->singleton(OAuth::class, fn (Application $app) => new OAuth($app['config']['monzo']));

        $this->app->singleton(TokenManager::class, fn (Application $app) => new TokenManager(
            $app->make(TokenStore::class),
            $app->make(OAuth::class),
            $app['config']['monzo'],
            $app['cache']->store(),
        ));

        $this->app->singleton(Client::class, fn (Application $app) => new Client(
            $app->make(TokenManager::class),
            $app['config']['monzo'],
        ));

        $this->app->singleton(Monzo::class, fn (Application $app) => new Monzo(
            $app->make(Client::class),
            $app->make(OAuth::class),
            $app->make(TokenManager::class),
            $app['config']['monzo'],
        ));

        $this->app->alias(Monzo::class, 'monzo');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/monzo.php' => config_path('monzo.php'),
            ], 'monzo-config');

            /*
             * Run for the database store only, so `php artisan migrate` is
             * all it needs -- and an app keeping the token somewhere of its
             * own does not get an empty monzo_tokens table it never uses. An
             * app that publishes the migration to change it turns this off,
             * or it would run twice.
             */
            if (($this->app['config']['monzo.token_store.driver'] ?? 'database') === 'database'
                && filter_var($this->app['config']['monzo.token_store.migrations'] ?? true, FILTER_VALIDATE_BOOL)) {
                $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
            }

            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'monzo-migrations');

            $this->commands([
                Console\AuthorizeCommand::class,
                Console\WhoamiCommand::class,
                Console\AccountsCommand::class,
                Console\RefreshTokenCommand::class,
                Console\WebhooksCommand::class,
                Console\LogoutCommand::class,
            ]);
        }

        $this->registerRoutes();
    }

    protected function registerRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        $config = $this->app['config']['monzo'];

        if (filter_var($config['routes']['enabled'] ?? true, FILTER_VALIDATE_BOOL)) {
            Route::group([
                'prefix' => $config['routes']['prefix'] ?? 'monzo',
                'middleware' => $config['routes']['middleware'] ?? ['web', 'auth'],
            ], function () {
                Route::get('connect', [OAuthController::class, 'connect'])->name('monzo.connect');
                Route::get('callback', [OAuthController::class, 'callback'])->name('monzo.callback');
            });
        }

        if (filter_var($config['webhooks']['enabled'] ?? true, FILTER_VALIDATE_BOOL)) {
            Route::post(trim($config['webhooks']['path'] ?? 'monzo/webhook', '/').'/{secret?}', WebhookController::class)
                ->middleware($config['webhooks']['middleware'] ?? [])
                ->name('monzo.webhook');
        }
    }

    protected function makeTokenStore(Application $app): TokenStore
    {
        $config = $app['config']['monzo.token_store'] ?? [];
        $driver = $config['driver'] ?? 'file';

        return match ($driver) {
            'file' => new FileTokenStore(
                new Filesystem,
                $app['encrypter'],
                $config['path'] ?? storage_path('app/monzo/token.json'),
            ),
            'cache' => new CacheTokenStore(
                $app['cache']->store($config['cache_store'] ?? null),
                $app['encrypter'],
                $config['cache_key'] ?? 'monzo.token',
            ),
            'database' => new DatabaseTokenStore(
                $app['db']->connection($config['connection'] ?? null),
                $app['encrypter'],
                $config['table'] ?? 'monzo_tokens',
                $config['key'] ?? 'default',
            ),
            'array' => new ArrayTokenStore,
            default => $this->customTokenStore($app, $driver),
        };
    }

    /**
     * The app's own store, named by class in monzo.token_store.driver and
     * resolved from the container -- so it can keep the token wherever the
     * app keeps its others, such as a shared OAuth integrations table.
     */
    protected function customTokenStore(Application $app, string $driver): TokenStore
    {
        if (! class_exists($driver) && ! $app->bound($driver)) {
            throw new ConfigurationException("Unknown Monzo token store [{$driver}]. Use database, file, cache, array, or the class name of your own TokenStore.");
        }

        $store = $app->make($driver);

        if (! $store instanceof TokenStore) {
            throw new ConfigurationException(sprintf('The Monzo token store [%s] must implement %s.', $driver, TokenStore::class));
        }

        return $store;
    }
}
