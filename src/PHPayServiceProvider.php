<?php

namespace PHPay\Laravel;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use PHPay\Laravel\Console\GatewaysCommand;
use PHPay\Laravel\Http\Controllers\WebhookController;
use PHPay\Laravel\Http\Middleware\VerifyPHPayWebhook;
use PHPay\PHPay;

class PHPayServiceProvider extends ServiceProvider
{
    /**
     * bind the manager, and the core's entry point through it.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/phpay.php', 'phpay');

        $this->app->singleton(GatewayManager::class, static function ($app): GatewayManager {
            $config = $app->make(Repository::class);

            return new GatewayManager($config);
        });

        /* injetar PHPay direto num controller resolve o gateway padrão */
        $this->app->bind(PHPay::class, static function ($app): PHPay {
            return $app->make(GatewayManager::class)->gateway();
        });
    }

    /**
     * publish the config, register the route, the middleware and the command.
     *
     * @return void
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/phpay.php' => $this->app->configPath('phpay.php'),
            ], 'phpay-config');

            $this->commands([GatewaysCommand::class]);
        }

        $this->registerWebhookRoute();
    }

    /**
     * the webhook route, when the application asked for one.
     *
     * `route` as null means the application handles webhooks in its own
     * controller, which is the right choice whenever the notification has to
     * do more than fan out events.
     *
     * @return void
     */
    private function registerWebhookRoute(): void
    {
        $config = $this->app->make(Repository::class);

        $route = $config->get('phpay.webhooks.route');

        if (!is_string($route) || trim($route) === '') {
            return;
        }

        $middleware   = $config->get('phpay.webhooks.middleware');
        $middleware   = is_array($middleware) ? $middleware : ['api'];
        $middleware[] = VerifyPHPayWebhook::class;

        Route::post($route, WebhookController::class)
            ->middleware($middleware)
            ->name('phpay.webhook');
    }
}
