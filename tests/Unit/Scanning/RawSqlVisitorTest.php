<?php

declare(strict_types=1);

namespace SchemaGuard\Tests\Unit\Scanning;

use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\ParserFactory;
use SchemaGuard\Scanning\ParsedFile;
use SchemaGuard\Scanning\Visitors\RawSqlVisitor;
use SchemaGuard\ValueObjects\Confidence;
use SchemaGuard\ValueObjects\SurfaceType;

final class RawSqlVisitorTest extends ScanningTestCase
{
    public function test_bare_column_raw_sql_match_is_medium_confidence(): void
    {
        $usages = $this->scanRaw("DB::select('SELECT phone, email FROM users');", 'users.phone');

        $this->assertNotEmpty($usages);
        $this->assertSame(SurfaceType::RAW_SQL, $usages[0]->surface);
        $this->assertSame(Confidence::MEDIUM, $usages[0]->confidence);
    }

    public function test_qualified_table_column_raw_sql_match_is_high_confidence(): void
    {
        $usages = $this->scanRaw("DB::select('SELECT users.phone FROM users');", 'users.phone');

        $this->assertNotEmpty($usages);
        $this->assertSame(SurfaceType::RAW_SQL, $usages[0]->surface);
        $this->assertSame(Confidence::HIGH, $usages[0]->confidence);
    }

    public function test_sql_identifier_decoys_do_not_match_substrings(): void
    {
        $decoy = $this->scanRaw("DB::select('SELECT telephone, microphone, phone_number FROM directory');", 'users.phone');

        $this->assertCount(
            0,
            $decoy,
            'Substring match (telephone/microphone/phone_number) must not register as a phone column usage',
        );
    }

    public function test_builder_raw_methods_are_scanned(): void
    {
        $usages = $this->scanRaw('$query->selectRaw(\'phone, email\')->whereRaw(\'users.phone IS NOT NULL\');', 'users.phone');
        $confidences = array_map(static fn ($usage): Confidence => $usage->confidence, $usages);
        usort($confidences, static fn (Confidence $left, Confidence $right): int => $left->value <=> $right->value);

        $this->assertCount(2, $usages);
        $this->assertSame([Confidence::MEDIUM, Confidence::HIGH], $confidences);
    }

    public function test_dynamic_raw_sql_records_diagnostic_without_usage(): void
    {
        $visitor = new RawSqlVisitor();
        $file = $this->parsed('<?php DB::select($sql); $query->whereRaw($sql);');

        $visitor->reset($file, $this->targets('users.phone'));
        (new NodeTraverser($visitor))->traverse($file->ast ?? []);

        $this->assertSame([], $visitor->usages());
        $this->assertCount(2, $visitor->diagnostics());
        $this->assertStringContainsString('Indeterminate raw SQL', $visitor->diagnostics()[0]);
    }

    /**
     * @return array<int, \SchemaGuard\ValueObjects\Usage>
     */
    private function scanRaw(string $php, string $target): array
    {
        return $this->runVisitor(new RawSqlVisitor(), $this->parsed('<?php ' . $php), $target);
    }

    private function parsed(string $source): ParsedFile
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($source) ?? [];
        $traverser = new NodeTraverser(
            new NameResolver(),
            new ParentConnectingVisitor(),
        );

        return ParsedFile::parsed('raw.php', $traverser->traverse($ast));
    }
}
