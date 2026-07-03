<?php

declare(strict_types=1);

namespace SchemaGuard\Console\Commands;

use Illuminate\Console\Command;
use SchemaGuard\Exceptions\CodebaseScanException;
use SchemaGuard\Exceptions\ConfigurationException;
use SchemaGuard\Output\ConsoleReporter;
use SchemaGuard\Output\ExitCodeResolver;
use SchemaGuard\Pipeline\AnalysisPipeline;
use SchemaGuard\Pipeline\AnalysisRequest;
use SchemaGuard\Pipeline\OutputFormat;
use SchemaGuard\Policy\PolicyConfiguration;
use Symfony\Component\Console\Helper\ProgressBar;

final class CheckCommand extends Command
{
    protected $signature = 'schemaguard:check
        {--path=* : PHP source paths to scan}
        {--migrations=* : Explicit migration file paths}
        {--diff : Analyze added or modified migration files from Git diff}
        {--base=HEAD : Git base ref used with --diff}
        {--format=console : Output format: console or json}
        {--strict : Treat warnings as CI failures}
        {--no-cache : Disable AST cache when available}';

    protected $description = 'Analyze pending or changed migrations and block destructive schema changes that break the codebase.';

    public function __construct(
        private readonly AnalysisPipeline $pipeline,
        private readonly ConsoleReporter $reporter,
        private readonly ExitCodeResolver $exitCodeResolver,
        private readonly PolicyConfiguration $config,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $request = null;

        try {
            $request = AnalysisRequest::fromCommandOptions($this->options(), $this->config);
            $run = $this->pipeline->run($request, $this->progressCallback($request));
            $exitCode = $this->exitCodeResolver->resolve($run->policyResult, $request->strict);
            $this->reporter->render($this->output, $run, $request, $exitCode);

            return $exitCode;
        } catch (CodebaseScanException|ConfigurationException $exception) {
            $this->reporter->renderFatal($this->output, $exception, $request?->format ?? $this->requestedFormat());

            return self::FAILURE;
        }
    }

    private function progressCallback(AnalysisRequest $request): ?callable
    {
        if ($request->format === OutputFormat::JSON) {
            return null;
        }

        $bar = null;

        return function (string $event, mixed $value) use (&$bar): void {
            if ($event === 'start' && is_int($value) && $value > 0) {
                $this->line('Indexing source files');
                $bar = $this->output->createProgressBar($value);
                $bar->start();

                return;
            }

            if ($event === 'advance' && $bar instanceof ProgressBar) {
                $bar->advance();

                return;
            }

            if ($event === 'finish' && $bar instanceof ProgressBar) {
                $bar->finish();
                $this->newLine(2);
            }
        };
    }

    private function requestedFormat(): OutputFormat
    {
        return strtolower((string) $this->option('format')) === 'json'
            ? OutputFormat::JSON
            : OutputFormat::CONSOLE;
    }
}
