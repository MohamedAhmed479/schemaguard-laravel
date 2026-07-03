<?php

declare(strict_types=1);

namespace SchemaGuard\Scanning;

final readonly class ResolvedType
{
    private function __construct(
        public string $kind,
        public ?string $name = null,
    ) {
    }

    public static function unknown(): self
    {
        return new self('unknown');
    }

    public static function model(string $fqcn): self
    {
        return new self('model', ltrim($fqcn, '\\'));
    }

    public static function table(string $table): self
    {
        return new self('table', $table);
    }

    public function isUnknown(): bool
    {
        return $this->kind === 'unknown';
    }

    public function isModel(): bool
    {
        return $this->kind === 'model';
    }

    public function isTable(): bool
    {
        return $this->kind === 'table';
    }
}
