<?php

declare(strict_types=1);

namespace SchemaGuard\Migrations;

interface GitCommandRunner
{
    /**
     * @param string[] $command
     */
    public function run(array $command, string $cwd): GitCommandResult;
}
