<?php

declare(strict_types=1);

namespace SchemaGuard;

use Illuminate\Support\ServiceProvider;
use SchemaGuard\Console\Commands\CheckCommand;

final class SchemaGuardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/schemaguard.php', 'schemaguard');
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__ . '/../config/schemaguard.php' => config_path('schemaguard.php'),
        ], 'schemaguard-config');

        $this->commands([
            CheckCommand::class,
        ]);
    }
}
