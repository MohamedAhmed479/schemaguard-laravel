<?php

declare(strict_types=1);

namespace SchemaGuard\Tests\Unit\Output;

use PHPUnit\Framework\Attributes\DataProvider;
use SchemaGuard\Output\ExitCodeResolver;
use SchemaGuard\Policy\EventFinding;
use SchemaGuard\Policy\PolicyConfiguration;
use SchemaGuard\Policy\PolicyResult;
use SchemaGuard\Tests\TestCase;
use SchemaGuard\ValueObjects\ChangeType;
use SchemaGuard\ValueObjects\ColumnReference;
use SchemaGuard\ValueObjects\SchemaChangeEvent;
use SchemaGuard\ValueObjects\Severity;
use SchemaGuard\ValueObjects\SourceLocation;

final class ExitCodeResolverTest extends TestCase
{
    #[DataProvider('exitCodeCases')]
    public function test_it_resolves_exit_codes(Severity $severity, bool $strict, bool $warningsAsFailure, int $warningExitCode, int $expected): void
    {
        $resolver = new ExitCodeResolver($this->config($warningsAsFailure, $warningExitCode));

        $this->assertSame($expected, $resolver->resolve($this->policyResult($severity), $strict));
    }

    /**
     * @return array<string, array{0:Severity,1:bool,2:bool,3:int,4:int}>
     */
    public static function exitCodeCases(): array
    {
        return [
            'safe is always zero' => [Severity::SAFE, false, false, 2, 0],
            'block is always one' => [Severity::BLOCK, false, false, 0, 1],
            'warning default can be zero' => [Severity::WARNING, false, false, 0, 0],
            'warning can use configured soft fail' => [Severity::WARNING, false, false, 2, 2],
            'warning strict is one' => [Severity::WARNING, true, false, 0, 1],
            'warning config hard fail is one' => [Severity::WARNING, false, true, 0, 1],
        ];
    }

    private function policyResult(Severity $severity): PolicyResult
    {
        if ($severity === Severity::SAFE) {
            return PolicyResult::empty();
        }

        return new PolicyResult([
            new EventFinding(
                SchemaChangeEvent::indeterminate(
                    ChangeType::COLUMN_DROPPED,
                    null,
                    'test',
                    new SourceLocation('migration.php', 1),
                ),
                [],
                $severity,
                [],
            ),
        ]);
    }

    private function config(bool $warningsAsFailure, int $warningExitCode): PolicyConfiguration
    {
        return PolicyConfiguration::fromArray([
            'scan_paths' => [],
            'migration_paths' => [],
            'policy' => [
                'modes' => [],
                'escalate_exposed_to_block' => false,
                'block_confidence_floor' => 'high',
            ],
            'ignore_paths' => [],
            'ignore' => ['tables' => [], 'columns' => []],
            'enforce' => ['tables' => [], 'columns' => []],
            'custom_rules' => [],
            'exit_codes' => [
                'warning_exit_code' => $warningExitCode,
                'treat_warnings_as_failure' => $warningsAsFailure,
            ],
        ]);
    }
}
