<?php

declare(strict_types=1);

namespace SchemaGuard\Policy;

use SchemaGuard\Graph\ImpactPath;
use SchemaGuard\ValueObjects\SchemaChangeEvent;
use SchemaGuard\ValueObjects\Severity;
use SchemaGuard\ValueObjects\Usage;

final readonly class EventFinding
{
    /**
     * @param Usage[] $usages
     * @param ImpactPath[] $paths
     */
    public function __construct(
        public SchemaChangeEvent $event,
        public array $usages,
        public Severity $severity,
        public array $paths,
    ) {
    }
}
