<?php

declare(strict_types=1);

namespace SchemaGuard\Tests\Feature;

use SchemaGuard\Tests\TestCase;

final class CheckCommandTest extends TestCase
{
    public function test_command_is_registered_and_runs_successfully(): void
    {
        $this->assertSame(['app', 'routes', 'database/factories', 'database/seeders'], config('schemaguard.scan_paths'));

        $this->artisan('schemaguard:check')
            ->expectsOutputToContain('Deployment Firewall')
            ->assertExitCode(0);
    }
}
