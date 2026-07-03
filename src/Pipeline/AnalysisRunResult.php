<?php

declare(strict_types=1);

namespace SchemaGuard\Pipeline;

use SchemaGuard\Policy\PolicyResult;

final readonly class AnalysisRunResult
{
    public function __construct(
        public PolicyResult $policyResult,
        public AnalysisMetadata $metadata,
    ) {
    }
}
