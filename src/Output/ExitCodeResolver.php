<?php

declare(strict_types=1);

namespace SchemaGuard\Output;

use SchemaGuard\Policy\PolicyConfiguration;
use SchemaGuard\Policy\PolicyResult;
use SchemaGuard\ValueObjects\Severity;

final readonly class ExitCodeResolver
{
    public function __construct(private PolicyConfiguration $config)
    {
    }

    public function resolve(PolicyResult $result, bool $strict): int
    {
        return match ($result->overall) {
            Severity::BLOCK => 1,
            Severity::WARNING => ($strict || $this->config->treatWarningsAsFailure())
                ? 1
                : $this->config->warningExitCode(),
            Severity::SAFE => 0,
        };
    }
}
