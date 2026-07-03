<?php

declare(strict_types=1);

namespace SchemaGuard\Tests\Unit\Scanning;

use SchemaGuard\Scanning\StaticAnalysisScanner;
use SchemaGuard\ValueObjects\Confidence;
use SchemaGuard\ValueObjects\SurfaceType;

final class StaticAnalysisScannerTest extends ScanningTestCase
{
    public function test_it_runs_two_pass_scanning_and_returns_usage_surfaces(): void
    {
        $index = $this->index([
            'Models',
            'Http/Resources/UserResource.php',
            'Http/Controllers/UserController.php',
        ]);

        $scanner = new StaticAnalysisScanner();
        $usages = $scanner->scan($index, [$this->targetEvent('users.phone')]);

        $this->assertSame('users', $scanner->modelTableMap()->tableForModel('SchemaGuard\Tests\Fixtures\Models\User'));
        $this->assertTrue($this->hasUsage($usages, SurfaceType::MODEL_SCHEMA, Confidence::DEFINITIVE));
        $this->assertTrue($this->hasUsage($usages, SurfaceType::ELOQUENT_QUERY, Confidence::DEFINITIVE));
        $this->assertTrue($this->hasUsage($usages, SurfaceType::API_RESOURCE, Confidence::DEFINITIVE));
        $this->assertTrue($this->hasUsage($usages, SurfaceType::CONTROLLER, Confidence::HIGH, 'validate()'));
        $this->assertTrue($this->hasUsage($usages, SurfaceType::CONTROLLER, Confidence::MEDIUM, 'input()'));
    }

    public function test_false_positive_fixture_yields_zero_usages_for_target_column(): void
    {
        $usages = (new StaticAnalysisScanner())->scan(
            $this->index(['false_positive.php']),
            [$this->targetEvent('users.phone')],
        );

        $this->assertCount(
            0,
            $usages,
            'Coincidental string, arbitrary array key, and local variable must not be treated as a column usage.',
        );
    }

    public function test_scanner_only_emits_symbols_from_schema_change_targets(): void
    {
        $usages = (new StaticAnalysisScanner())->scan(
            $this->index([
                'Models/User.php',
                'Http/Resources/UserResource.php',
                'Http/Controllers/UserController.php',
                'false_positive.php',
            ]),
            [$this->targetEvent('users.phone')],
        );

        $this->assertNotSame([], $usages);
        foreach ($usages as $usage) {
            $this->assertSame('column:users.phone', $usage->symbol->id());
        }
    }

    public function test_dedupe_keeps_strongest_confidence_for_same_symbol_and_location(): void
    {
        $index = $this->index([
            'Models',
            'Http/Controllers/DedupeController.php',
        ]);

        $usages = (new StaticAnalysisScanner())->scan($index, [$this->targetEvent('users.phone')]);
        $dedupeUsages = array_values(array_filter(
            $usages,
            static fn ($usage): bool => str_ends_with($usage->location->file, 'DedupeController.php')
                && $usage->location->line === 14,
        ));

        $this->assertCount(1, $dedupeUsages);
        $this->assertSame(Confidence::DEFINITIVE, $dedupeUsages[0]->confidence);
        $this->assertSame(SurfaceType::ELOQUENT_QUERY, $dedupeUsages[0]->surface);
    }

    public function test_dedupe_does_not_merge_distinct_source_locations(): void
    {
        $index = $this->index([
            'Models',
            'Http/Controllers/DedupeController.php',
        ]);

        $usages = (new StaticAnalysisScanner())->scan($index, [$this->targetEvent('users.phone')]);
        $queryUsages = array_values(array_filter(
            $usages,
            static fn ($usage): bool => str_ends_with($usage->location->file, 'DedupeController.php')
                && $usage->surface === SurfaceType::ELOQUENT_QUERY
                && $usage->detail === 'where()',
        ));

        $this->assertCount(3, $queryUsages);
        $this->assertCount(3, array_unique(array_map(
            static fn ($usage): int => $usage->location->line,
            $queryUsages,
        )));
    }

    public function test_failed_parsed_files_are_skipped_without_aborting_scans(): void
    {
        $index = $this->index([
            'Models/User.php',
            'broken_syntax.php',
        ]);

        $this->assertFalse($index[$this->fixture('broken_syntax.php')]->parsed);

        $usages = (new StaticAnalysisScanner())->scan($index, [$this->targetEvent('users.phone')]);

        $this->assertNotSame([], $usages);
    }

    /**
     * @param array<int, \SchemaGuard\ValueObjects\Usage> $usages
     */
    private function hasUsage(array $usages, SurfaceType $surface, Confidence $confidence, ?string $detail = null): bool
    {
        foreach ($usages as $usage) {
            if ($usage->surface !== $surface || $usage->confidence !== $confidence) {
                continue;
            }

            if ($detail === null || $usage->detail === $detail) {
                return true;
            }
        }

        return false;
    }
}
