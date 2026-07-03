<?php

declare(strict_types=1);

namespace SchemaGuard\Policy;

use SchemaGuard\Graph\DependencyGraph;
use SchemaGuard\Graph\ImpactPath;
use SchemaGuard\ValueObjects\ChangeType;
use SchemaGuard\ValueObjects\ColumnReference;
use SchemaGuard\ValueObjects\Confidence;
use SchemaGuard\ValueObjects\SchemaChangeEvent;
use SchemaGuard\ValueObjects\Severity;
use SchemaGuard\ValueObjects\TableReference;
use SchemaGuard\ValueObjects\Usage;

final readonly class PolicyEngine
{
    public function __construct(private PolicyConfiguration $config)
    {
    }

    /**
     * @param SchemaChangeEvent[] $events
     * @param Usage[] $usages
     */
    public function evaluate(array $events, array $usages, DependencyGraph $graph): PolicyResult
    {
        $findings = [];
        $diagnostics = [];

        foreach ($events as $event) {
            if (! $event instanceof SchemaChangeEvent) {
                continue;
            }

            $relevant = $this->usagesFor($event, $usages);
            $peak = $this->peakConfidence($relevant);
            $exposed = $this->reachesExposed($event, $relevant, $graph);
            $severity = $event->neutralized ? Severity::SAFE : $this->severityFor($event, $peak);

            if (! $event->neutralized && $severity === Severity::WARNING && $exposed && $this->config->escalateExposedToBlock()) {
                $severity = Severity::BLOCK;
            }

            if (! $event->neutralized) {
                $severity = $this->config->applyOverrides($event, $severity);
            }

            if ($event->indeterminate) {
                $diagnostics[] = sprintf(
                    'Indeterminate %s at %s:%d: %s',
                    $event->type->value,
                    $event->location->file,
                    $event->location->line,
                    $event->reason ?? 'manual review required',
                );
            }

            if ($event->neutralized) {
                $diagnostics[] = sprintf(
                    'Neutralized %s at %s:%d: %s',
                    $event->type->value,
                    $event->location->file,
                    $event->location->line,
                    $event->reason ?? 'same-migration re-add',
                );
            }

            $findings[] = new EventFinding(
                $event,
                $relevant,
                $severity,
                $this->pathsFor($event, $relevant, $graph),
            );
        }

        return new PolicyResult($findings, $diagnostics);
    }

    /**
     * @param Usage[] $usages
     *
     * @return Usage[]
     */
    private function usagesFor(SchemaChangeEvent $event, array $usages): array
    {
        return array_values(array_filter(
            $usages,
            fn (Usage $usage): bool => $this->usageMatchesEvent($usage, $event),
        ));
    }

    private function usageMatchesEvent(Usage $usage, SchemaChangeEvent $event): bool
    {
        if ($event->column !== null) {
            return $usage->symbol instanceof ColumnReference && $usage->symbol->equals($event->column);
        }

        $table = $event->table?->table;
        if ($table === null) {
            return false;
        }

        return match (true) {
            $usage->symbol instanceof TableReference => $usage->symbol->table === $table,
            $usage->symbol instanceof ColumnReference => $usage->symbol->table === $table,
            default => false,
        };
    }

    /**
     * @param Usage[] $usages
     */
    private function peakConfidence(array $usages): ?Confidence
    {
        $peak = null;

        foreach ($usages as $usage) {
            if ($peak === null || $usage->confidence->value > $peak->value) {
                $peak = $usage->confidence;
            }
        }

        return $peak;
    }

    private function severityFor(SchemaChangeEvent $event, ?Confidence $peak): Severity
    {
        if ($event->indeterminate) {
            return Severity::WARNING;
        }

        if ($peak === null) {
            return Severity::SAFE;
        }

        if ($event->type === ChangeType::COLUMN_TYPE_CHANGED) {
            return Severity::WARNING;
        }

        return $peak->atLeast($this->config->blockConfidenceFloor())
            ? Severity::BLOCK
            : Severity::WARNING;
    }

    /**
     * @param Usage[] $relevant
     */
    private function reachesExposed(SchemaChangeEvent $event, array $relevant, DependencyGraph $graph): bool
    {
        if ($event->column !== null) {
            return $graph->reachesExposedSurface($event->column->id());
        }

        foreach ($relevant as $usage) {
            if ($usage->symbol instanceof ColumnReference && $graph->reachesExposedSurface($usage->symbol->id())) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param Usage[] $relevant
     *
     * @return ImpactPath[]
     */
    private function pathsFor(SchemaChangeEvent $event, array $relevant, DependencyGraph $graph): array
    {
        if ($event->column !== null) {
            return $graph->exposedPaths($event->column->id());
        }

        $paths = [];

        foreach ($relevant as $usage) {
            if (! $usage->symbol instanceof ColumnReference) {
                continue;
            }

            foreach ($graph->exposedPaths($usage->symbol->id()) as $path) {
                $paths[$path->id()] = $path;
            }
        }

        ksort($paths);

        return array_values($paths);
    }
}
