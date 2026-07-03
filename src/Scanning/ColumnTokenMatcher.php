<?php

declare(strict_types=1);

namespace SchemaGuard\Scanning;

use SchemaGuard\ValueObjects\Confidence;
use SchemaGuard\ValueObjects\Rarity;

final class ColumnTokenMatcher
{
    /**
     * @param string[]|null $commonColumnNames
     */
    public function __construct(private readonly ?array $commonColumnNames = null)
    {
    }

    public function rarity(string $column): Rarity
    {
        if (in_array($column, $this->commonColumnNames(), true)) {
            return Rarity::COMMON;
        }

        if (str_contains($column, '_') || strlen($column) >= 12) {
            return Rarity::RARE;
        }

        return Rarity::MODERATE;
    }

    public function confidenceForUnresolved(string $column): Confidence
    {
        return match ($this->rarity($column)) {
            Rarity::COMMON => Confidence::LOW,
            Rarity::MODERATE, Rarity::RARE => Confidence::MEDIUM,
        };
    }

    public function matchesInSql(string $sql, string $token): bool
    {
        $pattern = '/(?<![A-Za-z0-9_])' . preg_quote($token, '/') . '(?![A-Za-z0-9_])/i';

        return (bool) preg_match($pattern, $sql);
    }

    /**
     * @return string[]
     */
    private function commonColumnNames(): array
    {
        return $this->commonColumnNames
            ?? config('schemaguard.common_column_names', [
                'id',
                'name',
                'email',
                'phone',
                'type',
                'status',
            ]);
    }
}
