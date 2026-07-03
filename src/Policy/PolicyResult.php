<?php

declare(strict_types=1);

namespace SchemaGuard\Policy;

use InvalidArgumentException;
use SchemaGuard\ValueObjects\Severity;

final readonly class PolicyResult
{
    /** @var EventFinding[] */
    public array $findings;

    public Severity $overall;

    public int $blockCount;

    public int $warningCount;

    public int $safeCount;

    /** @var string[] */
    public array $diagnostics;

    /**
     * @param EventFinding[] $findings
     * @param string[] $diagnostics
     */
    public function __construct(array $findings, array $diagnostics = [])
    {
        $blockCount = 0;
        $warningCount = 0;
        $safeCount = 0;
        $overall = Severity::SAFE;

        foreach ($findings as $finding) {
            if (! $finding instanceof EventFinding) {
                throw new InvalidArgumentException('PolicyResult findings must be EventFinding instances.');
            }

            match ($finding->severity) {
                Severity::BLOCK => $blockCount++,
                Severity::WARNING => $warningCount++,
                Severity::SAFE => $safeCount++,
            };

            if ($finding->severity->value > $overall->value) {
                $overall = $finding->severity;
            }
        }

        foreach ($diagnostics as $diagnostic) {
            if (! is_string($diagnostic)) {
                throw new InvalidArgumentException('PolicyResult diagnostics must be strings.');
            }
        }

        $this->findings = array_values($findings);
        $this->overall = $overall;
        $this->blockCount = $blockCount;
        $this->warningCount = $warningCount;
        $this->safeCount = $safeCount;
        $this->diagnostics = array_values($diagnostics);
    }

    public static function empty(): self
    {
        return new self([]);
    }
}
