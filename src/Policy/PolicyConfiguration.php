<?php

declare(strict_types=1);

namespace SchemaGuard\Policy;

use SchemaGuard\Exceptions\ConfigurationException;
use SchemaGuard\ValueObjects\ChangeType;
use SchemaGuard\ValueObjects\Confidence;
use SchemaGuard\ValueObjects\SchemaChangeEvent;
use SchemaGuard\ValueObjects\Severity;

final readonly class PolicyConfiguration
{
    /**
     * @param array<string, PolicyMode> $modes
     * @param string[] $ignoredPaths
     * @param array<string, true> $ignoredTables
     * @param array<string, true> $ignoredColumns
     * @param array<string, true> $enforcedTables
     * @param array<string, true> $enforcedColumns
     * @param CustomRule[] $customRules
     */
    private function __construct(
        private array $modes,
        private array $ignoredPaths,
        private array $ignoredTables,
        private array $ignoredColumns,
        private array $enforcedTables,
        private array $enforcedColumns,
        private array $customRules,
        private bool $escalateExposedToBlock,
        private Confidence $blockConfidenceFloor,
        private bool $treatWarningsAsFailure,
        private int $warningExitCode,
    ) {
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $policy = self::arrayValue($config, 'policy');
        $modes = self::parseModes(self::arrayValue($policy, 'modes'));

        foreach (ChangeType::cases() as $type) {
            $modes[$type->value] ??= $type === ChangeType::COLUMN_TYPE_CHANGED
                ? PolicyMode::WARN
                : PolicyMode::BLOCK;
        }

        $exitCodes = self::arrayValue($config, 'exit_codes');

        return new self(
            $modes,
            self::stringList(self::arrayValue($config, 'ignore_paths')),
            self::stringSet(self::arrayValue(self::arrayValue($config, 'ignore'), 'tables')),
            self::stringSet(self::arrayValue(self::arrayValue($config, 'ignore'), 'columns')),
            self::stringSet(self::arrayValue(self::arrayValue($config, 'enforce'), 'tables')),
            self::stringSet(self::arrayValue(self::arrayValue($config, 'enforce'), 'columns')),
            self::parseCustomRules(self::arrayValue($config, 'custom_rules')),
            (bool) ($policy['escalate_exposed_to_block'] ?? false),
            self::parseConfidence((string) ($policy['block_confidence_floor'] ?? 'high')),
            (bool) ($exitCodes['treat_warnings_as_failure'] ?? false),
            (int) ($exitCodes['warning_exit_code'] ?? 0),
        );
    }

    public function isIgnored(string|SchemaChangeEvent $target): bool
    {
        if (is_string($target) && $this->looksLikePath($target) && $this->pathIsIgnored($target)) {
            return true;
        }

        [$table, $column] = $this->targetParts($target);

        if ($table !== null && isset($this->ignoredTables[$table])) {
            return true;
        }

        return $table !== null && $column !== null && isset($this->ignoredColumns["{$table}.{$column}"]);
    }

    public function isEnforced(string|SchemaChangeEvent $target): bool
    {
        [$table, $column] = $this->targetParts($target);

        if ($table !== null && isset($this->enforcedTables[$table])) {
            return true;
        }

        return $table !== null && $column !== null && isset($this->enforcedColumns["{$table}.{$column}"]);
    }

    public function mode(ChangeType $type): PolicyMode
    {
        return $this->modes[$type->value] ?? PolicyMode::BLOCK;
    }

    /**
     * @return CustomRule[]
     */
    public function customRules(): array
    {
        return $this->customRules;
    }

    public function escalateExposedToBlock(): bool
    {
        return $this->escalateExposedToBlock;
    }

    public function blockConfidenceFloor(): Confidence
    {
        return $this->blockConfidenceFloor;
    }

    public function treatWarningsAsFailure(): bool
    {
        return $this->treatWarningsAsFailure;
    }

    public function warningExitCode(): int
    {
        return $this->warningExitCode;
    }

    public function applyOverrides(SchemaChangeEvent $event, Severity $severity): Severity
    {
        if ($this->isIgnored($event)) {
            $severity = Severity::SAFE;
        }

        if ($this->isEnforced($event)) {
            $severity = Severity::BLOCK;
        }

        $severity = $this->applyMode($this->mode($event->type), $severity);

        foreach ($this->customRules as $rule) {
            if ($rule->matches($event)) {
                $severity = $rule->severity;
            }
        }

        return $severity;
    }

    private function applyMode(PolicyMode $mode, Severity $severity): Severity
    {
        return match ($mode) {
            PolicyMode::BLOCK => $severity,
            PolicyMode::WARN => $severity === Severity::BLOCK ? Severity::WARNING : $severity,
            PolicyMode::OFF => Severity::SAFE,
        };
    }

    /**
     * @return array{0:?string,1:?string}
     */
    private function targetParts(string|SchemaChangeEvent $target): array
    {
        if ($target instanceof SchemaChangeEvent) {
            return [
                $target->column?->table ?? $target->table?->table,
                $target->column?->column,
            ];
        }

        $target = preg_replace('/^(column|table):/', '', $target) ?? $target;

        if (str_contains($target, '.')) {
            [$table, $column] = explode('.', $target, 2);

            return [$table, $column];
        }

        return [$target === '' ? null : $target, null];
    }

    private function pathIsIgnored(string $path): bool
    {
        $normalizedPath = str_replace('\\', '/', $path);

        foreach ($this->ignoredPaths as $pattern) {
            $normalizedPattern = str_replace('\\', '/', $pattern);

            if (fnmatch($normalizedPattern, $normalizedPath, FNM_NOESCAPE)) {
                return true;
            }

            if (! str_starts_with($normalizedPattern, '*') && fnmatch('*/' . $normalizedPattern, $normalizedPath, FNM_NOESCAPE)) {
                return true;
            }
        }

        return false;
    }

    private function looksLikePath(string $target): bool
    {
        return str_contains($target, '/') || str_contains($target, '\\');
    }

    /**
     * @param array<string, mixed> $modes
     *
     * @return array<string, PolicyMode>
     */
    private static function parseModes(array $modes): array
    {
        $parsed = [];

        foreach ($modes as $type => $mode) {
            $changeType = ChangeType::tryFrom((string) $type);
            if ($changeType === null) {
                throw new ConfigurationException("Unknown policy mode change type [{$type}].");
            }

            $policyMode = PolicyMode::tryFrom((string) $mode);
            if ($policyMode === null) {
                throw new ConfigurationException("Invalid policy mode [{$mode}] for [{$type}].");
            }

            $parsed[$changeType->value] = $policyMode;
        }

        return $parsed;
    }

    /**
     * @param array<int, mixed> $rules
     *
     * @return CustomRule[]
     */
    private static function parseCustomRules(array $rules): array
    {
        $parsed = [];

        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                throw new ConfigurationException('Custom rules must be arrays.');
            }

            $changeType = null;
            if (($rule['change_type'] ?? null) !== null) {
                $changeType = ChangeType::tryFrom((string) $rule['change_type']);
                if ($changeType === null) {
                    throw new ConfigurationException("Invalid custom rule change type [{$rule['change_type']}].");
                }
            }

            $severityValue = (string) ($rule['severity'] ?? '');
            $severity = Severity::tryFrom(self::severityValue($severityValue));
            if ($severity === null) {
                throw new ConfigurationException("Invalid custom rule severity [{$severityValue}].");
            }

            $parsed[] = new CustomRule(
                $changeType,
                isset($rule['table']) && $rule['table'] !== null ? (string) $rule['table'] : null,
                isset($rule['column']) && $rule['column'] !== null ? (string) $rule['column'] : null,
                $severity,
            );
        }

        return $parsed;
    }

    private static function parseConfidence(string $value): Confidence
    {
        return match (strtolower($value)) {
            'low' => Confidence::LOW,
            'medium' => Confidence::MEDIUM,
            'high' => Confidence::HIGH,
            'definitive' => Confidence::DEFINITIVE,
            default => throw new ConfigurationException("Invalid block confidence floor [{$value}]."),
        };
    }

    private static function severityValue(string $value): int
    {
        return match (strtolower($value)) {
            'safe' => Severity::SAFE->value,
            'warning', 'warn' => Severity::WARNING->value,
            'block' => Severity::BLOCK->value,
            default => -1,
        };
    }

    /**
     * @param array<int, mixed> $values
     *
     * @return array<string, true>
     */
    private static function stringSet(array $values): array
    {
        $set = [];

        foreach ($values as $value) {
            if (! is_string($value)) {
                throw new ConfigurationException('Configured table and column symbols must be strings.');
            }

            $set[$value] = true;
        }

        return $set;
    }

    /**
     * @param array<int, mixed> $values
     *
     * @return string[]
     */
    private static function stringList(array $values): array
    {
        foreach ($values as $value) {
            if (! is_string($value)) {
                throw new ConfigurationException('Configured ignore paths must be strings.');
            }
        }

        return array_values($values);
    }

    /**
     * @param array<string, mixed> $source
     *
     * @return array<string, mixed>
     */
    private static function arrayValue(array $source, string $key): array
    {
        $value = $source[$key] ?? [];

        if (! is_array($value)) {
            throw new ConfigurationException("Configuration key [{$key}] must be an array.");
        }

        return $value;
    }
}
