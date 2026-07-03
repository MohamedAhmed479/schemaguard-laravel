<?php

declare(strict_types=1);

namespace SchemaGuard\Output;

use SchemaGuard\Pipeline\AnalysisRequest;
use SchemaGuard\Pipeline\AnalysisRunResult;
use SchemaGuard\Pipeline\OutputFormat;
use SchemaGuard\Policy\EventFinding;
use SchemaGuard\ValueObjects\SchemaChangeEvent;
use SchemaGuard\ValueObjects\Severity;
use SchemaGuard\ValueObjects\Usage;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

final class ConsoleReporter
{
    public function render(
        OutputInterface $output,
        AnalysisRunResult $run,
        AnalysisRequest $request,
        int $exitCode,
    ): void {
        if ($request->format === OutputFormat::JSON) {
            $output->writeln($this->json($this->payload($run, $exitCode)));

            return;
        }

        $this->registerStyles($output);

        $io = new SymfonyStyle(new ArrayInput([]), $output);
        $result = $run->policyResult;
        $metadata = $run->metadata;

        $io->title('SchemaGuard - Deployment Firewall for Database Changes');
        $io->writeln(sprintf(
            'Analyzed %d migration(s) - %d source file(s) - %d file(s) unparseable',
            $metadata->migrationCount,
            $metadata->indexedFileCount,
            $metadata->unparsedFileCount,
        ));

        foreach ($this->sortedFindings($result->findings) as $finding) {
            $this->renderFinding($output, $io, $finding);
        }

        if ($result->diagnostics !== []) {
            $io->section('Diagnostics');
            foreach ($result->diagnostics as $diagnostic) {
                $io->writeln('- ' . $diagnostic);
            }
        }

        $tag = $this->styleTag($result->overall);
        $io->newLine();
        $io->writeln(sprintf(
            '<%s> RESULT: %s </%s>   %d blocking - %d warning - %d safe',
            $tag,
            $result->overall->name,
            $tag,
            $result->blockCount,
            $result->warningCount,
            $result->safeCount,
        ));
    }

    public function renderFatal(OutputInterface $output, Throwable $exception, OutputFormat $format = OutputFormat::CONSOLE): void
    {
        if ($format === OutputFormat::JSON) {
            $output->writeln($this->json([
                'schema_version' => '1.0',
                'overall' => 'ERROR',
                'exit_code' => 1,
                'error' => [
                    'type' => class_basename($exception),
                    'message' => $exception->getMessage(),
                ],
                'diagnostics' => [$exception->getMessage()],
            ]));

            return;
        }

        $io = new SymfonyStyle(new ArrayInput([]), $output);
        $io->error('SchemaGuard failed: ' . $exception->getMessage());
    }

    /**
     * @param EventFinding[] $findings
     *
     * @return EventFinding[]
     */
    private function sortedFindings(array $findings): array
    {
        usort($findings, static fn (EventFinding $left, EventFinding $right): int => [
            -$left->severity->value,
            $left->event->type->name,
            $left->event->column?->id() ?? $left->event->table?->id() ?? '',
        ] <=> [
            -$right->severity->value,
            $right->event->type->name,
            $right->event->column?->id() ?? $right->event->table?->id() ?? '',
        ]);

        return $findings;
    }

    private function renderFinding(OutputInterface $output, SymfonyStyle $io, EventFinding $finding): void
    {
        $tag = $this->styleTag($finding->severity);
        $event = $finding->event;

        $io->section(sprintf(
            '<%s>%s</%s> %s %s',
            $tag,
            $finding->severity->name,
            $tag,
            $event->type->name,
            $this->target($event),
        ));

        if ($finding->usages !== []) {
            $io->writeln(sprintf('Impacted usages (%d):', count($finding->usages)));
            $table = new Table($output);
            $table->setHeaders(['Surface', 'Location', 'Line', 'Confidence']);
            foreach ($finding->usages as $usage) {
                $table->addRow([
                    $usage->surface->name,
                    $usage->location->file,
                    (string) $usage->location->line,
                    $usage->confidence->name,
                ]);
            }
            $table->render();
        }

        if ($finding->paths !== []) {
            $io->writeln('Blast radius:');
            foreach ($finding->paths as $path) {
                $io->writeln('  <path>' . (string) $path . '</path>');
            }
        }
    }

    private function registerStyles(OutputInterface $output): void
    {
        $formatter = $output->getFormatter();
        $formatter->setStyle('block', new OutputFormatterStyle('white', 'red', ['bold']));
        $formatter->setStyle('warn', new OutputFormatterStyle('black', 'yellow', ['bold']));
        $formatter->setStyle('safe', new OutputFormatterStyle('black', 'green', ['bold']));
        $formatter->setStyle('path', new OutputFormatterStyle('cyan'));
    }

    private function styleTag(Severity $severity): string
    {
        return match ($severity) {
            Severity::BLOCK => 'block',
            Severity::WARNING => 'warn',
            Severity::SAFE => 'safe',
        };
    }

    private function target(SchemaChangeEvent $event): string
    {
        if ($event->column !== null) {
            $target = "{$event->column->table}.{$event->column->column}";

            if ($event->renamedTo !== null) {
                $target .= " -> {$event->renamedTo}";
            }

            if ($event->newType !== null) {
                $target .= " -> {$event->newType}";
            }

            return $target;
        }

        return $event->table?->table ?? 'indeterminate';
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(AnalysisRunResult $run, int $exitCode): array
    {
        $result = $run->policyResult;

        return [
            'schema_version' => '1.0',
            'overall' => $result->overall->name,
            'counts' => [
                'block' => $result->blockCount,
                'warning' => $result->warningCount,
                'safe' => $result->safeCount,
            ],
            'exit_code' => $exitCode,
            'analyzed' => [
                'migrations' => $run->metadata->migrationCount,
                'source_files' => $run->metadata->indexedFileCount,
                'unparsed_files' => $run->metadata->unparsedFileCount,
            ],
            'findings' => array_map(fn (EventFinding $finding): array => $this->findingPayload($finding), $this->sortedFindings($result->findings)),
            'diagnostics' => array_map(fn (string $diagnostic): string => $this->normalizeDiagnostic($diagnostic), $result->diagnostics),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function findingPayload(EventFinding $finding): array
    {
        $event = $finding->event;

        return [
            'change_type' => $event->type->name,
            'table' => $event->column?->table ?? $event->table?->table,
            'column' => $event->column?->column,
            'renamed_to' => $event->renamedTo,
            'new_type' => $event->newType,
            'severity' => $finding->severity->name,
            'indeterminate' => $event->indeterminate,
            'neutralized' => $event->neutralized,
            'reason' => $event->reason,
            'migration' => [
                'file' => $this->normalizePath($event->location->file),
                'line' => $event->location->line,
            ],
            'usages' => array_map(static fn (Usage $usage): array => [
                'surface' => $usage->surface->name,
                'file' => self::normalizeStaticPath($usage->location->file),
                'line' => $usage->location->line,
                'confidence' => $usage->confidence->name,
                'detail' => $usage->detail,
            ], $finding->usages),
            'impact_paths' => array_map(static fn ($path): string => (string) $path, $finding->paths),
        ];
    }

    private function normalizeDiagnostic(string $diagnostic): string
    {
        $normalized = str_replace('\\', '/', $diagnostic);

        foreach ($this->normalizationRoots() as $root) {
            $normalized = str_replace($root . '/', '', $normalized);
        }

        return $normalized;
    }

    private function normalizePath(string $path): string
    {
        return self::normalizeStaticPath($path);
    }

    private static function normalizeStaticPath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);

        foreach (self::staticNormalizationRoots() as $root) {
            $prefix = $root . '/';
            if (str_starts_with($normalized, $prefix)) {
                return substr($normalized, strlen($prefix));
            }
        }

        return $normalized;
    }

    /**
     * @return string[]
     */
    private function normalizationRoots(): array
    {
        return self::staticNormalizationRoots();
    }

    /**
     * @return string[]
     */
    private static function staticNormalizationRoots(): array
    {
        $roots = [
            getcwd() ?: '',
            base_path(),
        ];

        $normalized = [];
        foreach ($roots as $root) {
            $root = rtrim(str_replace('\\', '/', $root), '/');
            if ($root !== '') {
                $normalized[$root] = true;
            }
        }

        return array_keys($normalized);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function json(array $payload): string
    {
        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
