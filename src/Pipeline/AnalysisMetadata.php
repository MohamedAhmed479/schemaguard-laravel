<?php

declare(strict_types=1);

namespace SchemaGuard\Pipeline;

final readonly class AnalysisMetadata
{
    public function __construct(
        public int $migrationCount,
        public int $indexedFileCount,
        public int $unparsedFileCount,
    ) {
    }
}
