<?php

declare(strict_types=1);

namespace SchemaGuard\Migrations;

final readonly class GitCommandResult
{
    public function __construct(
        public int $exitCode,
        public string $output,
        public string $error,
    ) {
    }
}
