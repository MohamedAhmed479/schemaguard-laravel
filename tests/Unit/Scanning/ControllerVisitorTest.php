<?php

declare(strict_types=1);

namespace SchemaGuard\Tests\Unit\Scanning;

use SchemaGuard\Scanning\Visitors\ControllerVisitor;
use SchemaGuard\ValueObjects\Confidence;
use SchemaGuard\ValueObjects\SurfaceType;

final class ControllerVisitorTest extends ScanningTestCase
{
    public function test_request_validate_array_keys_are_controller_high_confidence(): void
    {
        $file = $this->index(['Http/Controllers/UserController.php'])[$this->fixture('Http/Controllers/UserController.php')];
        $usages = $this->runVisitor(new ControllerVisitor(), $file, 'users.phone');

        $this->assertTrue($this->hasUsage($usages, Confidence::HIGH, 'validate()'));
    }

    public function test_form_request_rules_array_keys_are_controller_high_confidence(): void
    {
        $file = $this->index(['Http/Requests/UserFormRequest.php'])[$this->fixture('Http/Requests/UserFormRequest.php')];
        $usages = $this->runVisitor(new ControllerVisitor(), $file, 'users.phone');

        $this->assertTrue($this->hasUsage($usages, Confidence::HIGH, 'rules()'));
    }

    public function test_request_input_and_property_access_are_controller_medium_confidence(): void
    {
        $file = $this->index(['Http/Controllers/UserController.php'])[$this->fixture('Http/Controllers/UserController.php')];
        $usages = $this->runVisitor(new ControllerVisitor(), $file, 'users.phone');

        $this->assertTrue($this->hasUsage($usages, Confidence::MEDIUM, 'input()'));
        $this->assertTrue($this->hasUsage($usages, Confidence::MEDIUM, 'only()'));
        $this->assertTrue($this->hasUsage($usages, Confidence::MEDIUM, '$request property'));
        $this->assertFalse($this->hasUsage($usages, Confidence::HIGH, 'input()'));
        $this->assertFalse($this->hasUsage($usages, Confidence::HIGH, 'only()'));
        $this->assertFalse($this->hasUsage($usages, Confidence::HIGH, '$request property'));
    }

    public function test_eloquent_query_detection_is_not_duplicated_by_controller_visitor(): void
    {
        $file = $this->index(['Http/Controllers/UserController.php'])[$this->fixture('Http/Controllers/UserController.php')];
        $usages = $this->runVisitor(new ControllerVisitor(), $file, 'users.phone');

        foreach ($usages as $usage) {
            $this->assertSame(SurfaceType::CONTROLLER, $usage->surface);
            $this->assertNotContains($usage->detail, ['where()', 'select()', 'orderBy()']);
        }
    }

    /**
     * @param array<int, \SchemaGuard\ValueObjects\Usage> $usages
     */
    private function hasUsage(array $usages, Confidence $confidence, string $detail): bool
    {
        foreach ($usages as $usage) {
            if ($usage->surface === SurfaceType::CONTROLLER && $usage->confidence === $confidence && $usage->detail === $detail) {
                return true;
            }
        }

        return false;
    }
}
