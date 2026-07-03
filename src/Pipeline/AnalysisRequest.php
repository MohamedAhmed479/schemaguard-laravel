<?php

declare(strict_types=1);

namespace SchemaGuard\Pipeline;

use SchemaGuard\Exceptions\ConfigurationException;
use SchemaGuard\Policy\PolicyConfiguration;

final readonly class AnalysisRequest
{
    /**
     * @param string[] $scanPaths
     * @param string[] $explicitMigrations
     */
    public function __construct(
        public array $scanPaths,
        public MigrationSource $migrationSource,
        public string $gitBase,
        public array $explicitMigrations,
        public OutputFormat $format,
        public bool $strict,
        public bool $useCache,
        public bool $scanPathsWereProvided = false,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function fromCommandOptions(array $options, PolicyConfiguration $config): self
    {
        $paths = self::stringList($options['path'] ?? []);
        $migrations = self::stringList($options['migrations'] ?? []);
        $diff = (bool) ($options['diff'] ?? false);

        if ($diff && $migrations !== []) {
            throw new ConfigurationException('Use either --diff or --migrations, not both.');
        }

        $formatValue = strtolower((string) ($options['format'] ?? 'console'));
        $format = OutputFormat::tryFrom($formatValue);
        if ($format === null) {
            throw new ConfigurationException("Invalid output format [{$formatValue}]. Supported formats are console and json.");
        }

        $base = trim((string) ($options['base'] ?? 'HEAD'));
        if ($base === '') {
            throw new ConfigurationException('Git base ref cannot be empty.');
        }

        return new self(
            scanPaths: $paths !== [] ? $paths : $config->scanPaths(),
            migrationSource: match (true) {
                $migrations !== [] => MigrationSource::EXPLICIT,
                $diff => MigrationSource::GIT_DIFF,
                default => MigrationSource::PENDING,
            },
            gitBase: $base,
            explicitMigrations: $migrations,
            format: $format,
            strict: (bool) ($options['strict'] ?? false),
            useCache: ! (bool) ($options['no-cache'] ?? false),
            scanPathsWereProvided: $paths !== [],
        );
    }

    /**
     * @return string[]
     */
    private static function stringList(mixed $value): array
    {
        if ($value === null || $value === false || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $value = [$value];
        }

        if (! is_array($value)) {
            throw new ConfigurationException('CLI path options must be strings.');
        }

        $strings = [];
        foreach ($value as $item) {
            if (! is_string($item)) {
                throw new ConfigurationException('CLI path options must be strings.');
            }

            $item = trim($item);
            if ($item !== '') {
                $strings[] = str_replace('\\', '/', $item);
            }
        }

        return array_values(array_unique($strings));
    }
}
