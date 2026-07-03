<?php

declare(strict_types=1);

namespace SchemaGuard;

use Illuminate\Support\ServiceProvider;
use SchemaGuard\Console\Commands\CheckCommand;
use SchemaGuard\Migrations\GitCommandRunner;
use SchemaGuard\Migrations\MigrationDiscovery;
use SchemaGuard\Migrations\NativeGitCommandRunner;
use SchemaGuard\Pipeline\AnalysisPipeline;
use SchemaGuard\Policy\PolicyConfiguration;

final class SchemaGuardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/schemaguard.php', 'schemaguard');

        $this->app->singleton(
            PolicyConfiguration::class,
            fn ($app): PolicyConfiguration => PolicyConfiguration::fromArray($app['config']->get('schemaguard')),
        );

        $this->app->singleton(GitCommandRunner::class, NativeGitCommandRunner::class);

        $this->app->singleton(
            MigrationDiscovery::class,
            fn ($app): MigrationDiscovery => new MigrationDiscovery(
                $app->make('files'),
                $app->make(PolicyConfiguration::class),
                $app->make(GitCommandRunner::class),
            ),
        );

        $this->app->singleton(AnalysisPipeline::class);
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
