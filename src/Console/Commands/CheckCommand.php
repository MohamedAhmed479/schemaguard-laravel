<?php

declare(strict_types=1);

namespace SchemaGuard\Console\Commands;

use Illuminate\Console\Command;

final class CheckCommand extends Command
{
    protected $signature = 'schemaguard:check';

    protected $description = 'Analyze pending or changed migrations and block destructive schema changes that break the codebase.';

    public function handle(): int
    {
        $this->line('SchemaGuard - Deployment Firewall for Database Changes');
        $this->info('No analysis wired yet.');

        return self::SUCCESS;
    }
}
