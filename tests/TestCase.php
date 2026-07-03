<?php

declare(strict_types=1);

namespace SchemaGuard\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use SchemaGuard\SchemaGuardServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            SchemaGuardServiceProvider::class,
        ];
    }
}
