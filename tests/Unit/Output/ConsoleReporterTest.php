<?php

declare(strict_types=1);

namespace SchemaGuard\Tests\Unit\Output;

use JsonException;
use RuntimeException;
use SchemaGuard\Output\ConsoleReporter;
use SchemaGuard\Pipeline\AnalysisMetadata;
use SchemaGuard\Pipeline\AnalysisRequest;
use SchemaGuard\Pipeline\AnalysisRunResult;
use SchemaGuard\Pipeline\MigrationSource;
use SchemaGuard\Pipeline\OutputFormat;
use SchemaGuard\Policy\EventFinding;
use SchemaGuard\Policy\PolicyResult;
use SchemaGuard\Tests\TestCase;
use SchemaGuard\ValueObjects\ChangeType;
use SchemaGuard\ValueObjects\ColumnReference;
use SchemaGuard\ValueObjects\Confidence;
use SchemaGuard\ValueObjects\SchemaChangeEvent;
use SchemaGuard\ValueObjects\Severity;
use SchemaGuard\ValueObjects\SourceLocation;
use SchemaGuard\ValueObjects\SurfaceType;
use SchemaGuard\ValueObjects\Usage;
use Symfony\Component\Console\Output\BufferedOutput;

final class ConsoleReporterTest extends TestCase
{
    public function test_console_report_contains_counts_usages_paths_and_result(): void
    {
        $output = new BufferedOutput();

        (new ConsoleReporter())->render($output, $this->runResult(), $this->request(OutputFormat::CONSOLE), 1);

        $rendered = $output->fetch();
        $this->assertStringContainsString('Deployment Firewall', $rendered);
        $this->assertStringContainsString('Impacted usages', $rendered);
        $this->assertStringContainsString('MODEL_SCHEMA', $rendered);
        $this->assertStringContainsString('users.phone → App\Models\User → GET /api/users/{user}', $rendered);
        $this->assertStringContainsString('RESULT: BLOCK', $rendered);
    }

    public function test_json_report_is_valid_machine_output_without_console_fragments(): void
    {
        $output = new BufferedOutput();

        (new ConsoleReporter())->render($output, $this->runResult(), $this->request(OutputFormat::JSON), 1);

        $rendered = $output->fetch();
        $decoded = json_decode($rendered, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('1.0', $decoded['schema_version']);
        $this->assertSame('BLOCK', $decoded['overall']);
        $this->assertSame(1, $decoded['exit_code']);
        $this->assertSame(1, $decoded['counts']['block']);
        $this->assertCount(1, $decoded['findings']);
        $this->assertStringNotContainsString('Deployment Firewall', $rendered);
        $this->assertStringNotContainsString('RESULT:', $rendered);
    }

    public function test_json_fatal_error_is_valid_json(): void
    {
        $output = new BufferedOutput();

        (new ConsoleReporter())->renderFatal($output, new RuntimeException('bad config'), OutputFormat::JSON);

        $decoded = json_decode($output->fetch(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('ERROR', $decoded['overall']);
        $this->assertSame(1, $decoded['exit_code']);
        $this->assertSame('bad config', $decoded['error']['message']);
    }

    private function runResult(): AnalysisRunResult
    {
        $event = SchemaChangeEvent::columnDropped(
            new ColumnReference('users', 'phone'),
            new SourceLocation('migration.php', 10),
        );

        return new AnalysisRunResult(
            new PolicyResult([
                new EventFinding(
                    $event,
                    [
                        new Usage(
                            new ColumnReference('users', 'phone'),
                            SurfaceType::MODEL_SCHEMA,
                            Confidence::DEFINITIVE,
                            new SourceLocation('app/Models/User.php', 12),
                            '$fillable',
                        ),
                    ],
                    Severity::BLOCK,
                    [
                        new \SchemaGuard\Graph\ImpactPath([
                            \SchemaGuard\Graph\GraphNode::column(new ColumnReference('users', 'phone')),
                            \SchemaGuard\Graph\GraphNode::model('App\Models\User'),
                            \SchemaGuard\Graph\GraphNode::route('GET', '/api/users/{user}'),
                        ]),
                    ],
                ),
            ]),
            new AnalysisMetadata(1, 3, 0),
        );
    }

    private function request(OutputFormat $format): AnalysisRequest
    {
        return new AnalysisRequest(
            scanPaths: ['app'],
            migrationSource: MigrationSource::EXPLICIT,
            gitBase: 'HEAD',
            explicitMigrations: ['migration.php'],
            format: $format,
            strict: false,
            useCache: true,
            scanPathsWereProvided: true,
        );
    }
}
