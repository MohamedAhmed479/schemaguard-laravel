<?php

declare(strict_types=1);

namespace SchemaGuard\Migrations;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use ParseError;
use SchemaGuard\Exceptions\MigrationParseException;
use SchemaGuard\ValueObjects\ChangeType;
use SchemaGuard\ValueObjects\ColumnReference;
use SchemaGuard\ValueObjects\SchemaChangeEvent;
use SchemaGuard\ValueObjects\SourceLocation;
use SchemaGuard\ValueObjects\TableReference;

final class MigrationParser
{
    /** @var string[] */
    private array $diagnostics = [];

    public function __construct(private readonly Filesystem $files)
    {
    }

    /**
     * @return string[]
     */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }

    /**
     * @param string[] $paths
     *
     * @return array<int, \SchemaGuard\ValueObjects\SchemaChangeEvent>
     */
    public function parseMany(array $paths): array
    {
        $this->diagnostics = [];
        $events = [];

        foreach ($paths as $path) {
            $events = array_merge($events, $this->parseSingleFile($path));
        }

        return $events;
    }

    /**
     * @return array<int, \SchemaGuard\ValueObjects\SchemaChangeEvent>
     */
    public function parseFile(string $path): array
    {
        $this->diagnostics = [];

        return $this->parseSingleFile($path);
    }

    /**
     * @return array<int, \SchemaGuard\ValueObjects\SchemaChangeEvent>
     */
    private function parseSingleFile(string $path): array
    {
        try {
            $tokens = $this->tokenize($path);
        } catch (MigrationParseException $exception) {
            $this->diagnostics[] = $exception->getMessage();

            return [];
        }

        return $this->parseTokens($tokens, $path);
    }

    /**
     * @return array<int, array{id:int|null,text:string,line:int}>
     */
    private function tokenize(string $path): array
    {
        if (! $this->files->exists($path)) {
            throw new MigrationParseException("Migration file not found: {$path}");
        }

        try {
            $source = $this->files->get($path);
        } catch (FileNotFoundException $exception) {
            throw new MigrationParseException("Migration file not readable: {$path}", previous: $exception);
        }

        try {
            $rawTokens = token_get_all($source, TOKEN_PARSE);
        } catch (ParseError $exception) {
            throw new MigrationParseException(
                "Could not parse migration {$path}: {$exception->getMessage()}",
                previous: $exception,
            );
        }

        $tokens = [];
        $line = 1;

        foreach ($rawTokens as $token) {
            if (is_array($token)) {
                $tokens[] = [
                    'id' => $token[0],
                    'text' => $token[1],
                    'line' => $token[2],
                ];
                $line = $token[2] + substr_count($token[1], "\n");

                continue;
            }

            $tokens[] = [
                'id' => null,
                'text' => $token,
                'line' => $line,
            ];
            $line += substr_count($token, "\n");
        }

        return $tokens;
    }

    /**
     * @param array<int, array{id:int|null,text:string,line:int}> $tokens
     *
     * @return array<int, SchemaChangeEvent>
     */
    private function parseTokens(array $tokens, string $path): array
    {
        $events = [];
        $currentTable = null;
        $insideUp = false;
        $pendingUpBody = false;
        $braceDepth = 0;
        $upBodyDepth = null;
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if ($token['id'] === T_FUNCTION && $this->nextFunctionName($tokens, $i) === 'up') {
                $pendingUpBody = true;
            }

            if ($token['text'] === '{') {
                $braceDepth++;

                if ($pendingUpBody) {
                    $insideUp = true;
                    $upBodyDepth = $braceDepth;
                    $pendingUpBody = false;
                    $currentTable = null;
                }

                continue;
            }

            if ($token['text'] === '}') {
                if ($insideUp && $upBodyDepth === $braceDepth) {
                    $insideUp = false;
                    $upBodyDepth = null;
                    $currentTable = null;
                }

                $braceDepth = max(0, $braceDepth - 1);

                continue;
            }

            if (! $insideUp || $token['id'] !== T_STRING) {
                continue;
            }

            $identifier = $token['text'];

            if ($identifier === 'Schema' && $this->isSchemaStaticCall($tokens, $i)) {
                $doubleColonIndex = $this->nextMeaningfulIndex($tokens, $i + 1);
                $schemaMethodIndex = $doubleColonIndex === null
                    ? null
                    : $this->nextMeaningfulIndex($tokens, $doubleColonIndex + 1);
                $schemaMethod = $schemaMethodIndex === null ? null : $tokens[$schemaMethodIndex]['text'];

                if (in_array($schemaMethod, ['table', 'create'], true)) {
                    $args = $this->callArguments($tokens, $schemaMethodIndex);
                    $currentTable = $this->literalString($args[0] ?? []);
                }

                if (in_array($schemaMethod, ['drop', 'dropIfExists'], true)) {
                    $args = $this->callArguments($tokens, $schemaMethodIndex);
                    $table = $this->literalString($args[0] ?? []);
                    $location = new SourceLocation($path, $tokens[$schemaMethodIndex]['line']);

                    if ($table === null) {
                        $events[] = SchemaChangeEvent::indeterminate(
                            ChangeType::TABLE_DROPPED,
                            null,
                            'dynamic table name',
                            $location,
                        );

                        continue;
                    }

                    $events[] = SchemaChangeEvent::tableDropped(new TableReference($table), $location);
                }

                continue;
            }

            if (in_array($identifier, ['dropColumn', 'dropColumns'], true)) {
                $args = $this->callArguments($tokens, $i);
                $tableReference = $currentTable === null ? null : new TableReference($currentTable);
                $location = new SourceLocation($path, $token['line']);

                foreach ($this->columnList($args[0] ?? []) as $column) {
                    if ($column === null || $currentTable === null) {
                        $events[] = SchemaChangeEvent::indeterminate(
                            ChangeType::COLUMN_DROPPED,
                            $tableReference,
                            $currentTable === null ? 'dynamic table name' : 'dynamic column name',
                            $location,
                        );

                        continue;
                    }

                    $events[] = SchemaChangeEvent::columnDropped(
                        new ColumnReference($currentTable, $column),
                        $location,
                    );
                }

                continue;
            }

            if ($identifier === 'renameColumn') {
                $args = $this->callArguments($tokens, $i);
                $from = $this->literalString($args[0] ?? []);
                $to = $this->literalString($args[1] ?? []);
                $tableReference = $currentTable === null ? null : new TableReference($currentTable);
                $location = new SourceLocation($path, $token['line']);

                if ($from === null || $currentTable === null) {
                    $events[] = SchemaChangeEvent::indeterminate(
                        ChangeType::COLUMN_RENAMED,
                        $tableReference,
                        $currentTable === null ? 'dynamic table name' : 'dynamic old column name',
                        $location,
                    );

                    continue;
                }

                $events[] = SchemaChangeEvent::columnRenamed(
                    new ColumnReference($currentTable, $from),
                    $to,
                    $location,
                );
            }
        }

        return $events;
    }

    /**
     * @param array<int, array{id:int|null,text:string,line:int}> $tokens
     */
    private function nextFunctionName(array $tokens, int $functionIndex): ?string
    {
        $next = $this->nextMeaningfulIndex($tokens, $functionIndex + 1);

        return $next !== null && $tokens[$next]['id'] === T_STRING ? $tokens[$next]['text'] : null;
    }

    /**
     * @param array<int, array{id:int|null,text:string,line:int}> $tokens
     */
    private function isSchemaStaticCall(array $tokens, int $schemaIndex): bool
    {
        $doubleColon = $this->nextMeaningfulIndex($tokens, $schemaIndex + 1);
        if ($doubleColon === null || $tokens[$doubleColon]['id'] !== T_DOUBLE_COLON) {
            return false;
        }

        $method = $this->nextMeaningfulIndex($tokens, $doubleColon + 1);

        return $method !== null && $tokens[$method]['id'] === T_STRING;
    }

    /**
     * @param array<int, array{id:int|null,text:string,line:int}> $tokens
     *
     * @return array<int, array<int, array{id:int|null,text:string,line:int}>>
     */
    private function callArguments(array $tokens, ?int $identifierIndex): array
    {
        if ($identifierIndex === null) {
            return [];
        }

        $open = $this->nextMeaningfulIndex($tokens, $identifierIndex + 1);
        if ($open === null || $tokens[$open]['text'] !== '(') {
            return [];
        }

        $args = [];
        $current = [];
        $depth = 0;
        $count = count($tokens);

        for ($i = $open + 1; $i < $count; $i++) {
            $token = $tokens[$i];

            if ($token['text'] === '(' || $token['text'] === '[') {
                $depth++;
                $current[] = $token;

                continue;
            }

            if ($token['text'] === ')' && $depth === 0) {
                if ($this->containsMeaningfulTokens($current)) {
                    $args[] = $current;
                }

                return $args;
            }

            if (($token['text'] === ')' || $token['text'] === ']') && $depth > 0) {
                $depth--;
                $current[] = $token;

                continue;
            }

            if ($token['text'] === ',' && $depth === 0) {
                $args[] = $current;
                $current = [];

                continue;
            }

            $current[] = $token;
        }

        return [];
    }

    /**
     * @param array<int, array{id:int|null,text:string,line:int}> $tokens
     */
    private function literalString(array $tokens): ?string
    {
        $meaningful = $this->meaningfulTokens($tokens);

        if (count($meaningful) !== 1 || $meaningful[0]['id'] !== T_CONSTANT_ENCAPSED_STRING) {
            return null;
        }

        return $this->unquote($meaningful[0]['text']);
    }

    /**
     * @param array<int, array{id:int|null,text:string,line:int}> $tokens
     *
     * @return array<int, string|null>
     */
    private function columnList(array $tokens): array
    {
        $literal = $this->literalString($tokens);
        if ($literal !== null) {
            return [$literal];
        }

        $meaningful = $this->meaningfulTokens($tokens);
        $first = $meaningful[0]['text'] ?? null;

        if ($first !== '[' && $first !== 'array') {
            return [null];
        }

        $columns = [];
        $hasDynamicItem = false;

        foreach ($meaningful as $token) {
            if ($token['id'] === T_CONSTANT_ENCAPSED_STRING) {
                $columns[] = $this->unquote($token['text']);

                continue;
            }

            if (in_array($token['text'], ['[', ']', '(', ')', ','], true) || $token['id'] === T_ARRAY) {
                continue;
            }

            $hasDynamicItem = true;
        }

        if ($hasDynamicItem) {
            $columns[] = null;
        }

        return $columns === [] ? [null] : $columns;
    }

    private function unquote(string $tokenText): string
    {
        return stripcslashes(substr($tokenText, 1, -1));
    }

    /**
     * @param array<int, array{id:int|null,text:string,line:int}> $tokens
     */
    private function nextMeaningfulIndex(array $tokens, int $start): ?int
    {
        $count = count($tokens);

        for ($i = $start; $i < $count; $i++) {
            if ($this->isMeaningful($tokens[$i])) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @param array<int, array{id:int|null,text:string,line:int}> $tokens
     *
     * @return array<int, array{id:int|null,text:string,line:int}>
     */
    private function meaningfulTokens(array $tokens): array
    {
        return array_values(array_filter($tokens, fn (array $token): bool => $this->isMeaningful($token)));
    }

    /**
     * @param array<int, array{id:int|null,text:string,line:int}> $tokens
     */
    private function containsMeaningfulTokens(array $tokens): bool
    {
        return $this->meaningfulTokens($tokens) !== [];
    }

    /**
     * @param array{id:int|null,text:string,line:int} $token
     */
    private function isMeaningful(array $token): bool
    {
        return ! in_array($token['id'], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
    }
}
