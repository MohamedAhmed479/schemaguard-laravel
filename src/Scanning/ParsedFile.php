<?php

declare(strict_types=1);

namespace SchemaGuard\Scanning;

final readonly class ParsedFile
{
    /**
     * @param array<int, \PhpParser\Node>|null $ast
     */
    private function __construct(
        public string $path,
        public ?array $ast,
        public bool $parsed,
        public ?string $error,
    ) {
    }

    /**
     * @param array<int, \PhpParser\Node> $ast
     */
    public static function parsed(string $path, array $ast): self
    {
        return new self($path, $ast, true, null);
    }

    public static function failed(string $path, string $error): self
    {
        return new self($path, null, false, $error);
    }
}
