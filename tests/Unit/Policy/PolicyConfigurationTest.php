<?php

declare(strict_types=1);

namespace SchemaGuard\Tests\Unit\Policy;

use SchemaGuard\Exceptions\ConfigurationException;
use SchemaGuard\Policy\PolicyConfiguration;
use SchemaGuard\Policy\PolicyMode;
use SchemaGuard\Tests\TestCase;
use SchemaGuard\ValueObjects\ChangeType;
use SchemaGuard\ValueObjects\Confidence;

final class PolicyConfigurationTest extends TestCase
{
    public function test_it_parses_typed_policy_configuration(): void
    {
        $config = PolicyConfiguration::fromArray($this->config([
            'policy' => [
                'modes' => [
                    'column_dropped' => 'warn',
                ],
                'escalate_exposed_to_block' => true,
                'block_confidence_floor' => 'definitive',
            ],
            'ignore' => [
                'tables' => ['legacy_logs'],
                'columns' => ['users.phone'],
            ],
            'ignore_paths' => [
                '*/vendor/*',
            ],
            'enforce' => [
                'tables' => ['payments'],
                'columns' => ['users.email'],
            ],
            'exit_codes' => [
                'warning_exit_code' => 2,
                'treat_warnings_as_failure' => true,
            ],
        ]));

        $this->assertSame(PolicyMode::WARN, $config->mode(ChangeType::COLUMN_DROPPED));
        $this->assertTrue($config->isIgnored('legacy_logs'));
        $this->assertTrue($config->isIgnored('users.phone'));
        $this->assertTrue($config->isIgnored('/project/vendor/package/file.php'));
        $this->assertTrue($config->isEnforced('payments'));
        $this->assertTrue($config->isEnforced('users.email'));
        $this->assertTrue($config->escalateExposedToBlock());
        $this->assertSame(Confidence::DEFINITIVE, $config->blockConfidenceFloor());
        $this->assertTrue($config->treatWarningsAsFailure());
        $this->assertSame(2, $config->warningExitCode());
    }

    public function test_it_rejects_invalid_policy_modes(): void
    {
        $this->expectException(ConfigurationException::class);

        PolicyConfiguration::fromArray($this->config([
            'policy' => [
                'modes' => [
                    'column_dropped' => 'maybe',
                ],
            ],
        ]));
    }

    public function test_it_rejects_unknown_policy_mode_change_types(): void
    {
        $this->expectException(ConfigurationException::class);

        PolicyConfiguration::fromArray($this->config([
            'policy' => [
                'modes' => [
                    'unknown_change' => 'block',
                ],
            ],
        ]));
    }

    public function test_it_rejects_invalid_block_confidence_floor(): void
    {
        $this->expectException(ConfigurationException::class);

        PolicyConfiguration::fromArray($this->config([
            'policy' => [
                'block_confidence_floor' => 'maybe',
            ],
        ]));
    }

    public function test_it_rejects_invalid_custom_rule_change_type(): void
    {
        $this->expectException(ConfigurationException::class);

        PolicyConfiguration::fromArray($this->config([
            'custom_rules' => [
                ['change_type' => 'maybe', 'table' => 'users', 'column' => 'phone', 'severity' => 'block'],
            ],
        ]));
    }

    public function test_it_rejects_invalid_custom_rule_severity(): void
    {
        $this->expectException(ConfigurationException::class);

        PolicyConfiguration::fromArray($this->config([
            'custom_rules' => [
                ['change_type' => 'column_dropped', 'table' => 'users', 'column' => 'phone', 'severity' => 'maybe'],
            ],
        ]));
    }

    public function test_policy_configuration_is_bound_as_a_container_singleton(): void
    {
        $first = $this->app->make(PolicyConfiguration::class);
        $second = $this->app->make(PolicyConfiguration::class);

        $this->assertInstanceOf(PolicyConfiguration::class, $first);
        $this->assertSame($first, $second);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function config(array $overrides = []): array
    {
        return array_replace_recursive([
            'policy' => [
                'modes' => [],
                'escalate_exposed_to_block' => false,
                'block_confidence_floor' => 'high',
            ],
            'ignore_paths' => [],
            'ignore' => [
                'tables' => [],
                'columns' => [],
            ],
            'enforce' => [
                'tables' => [],
                'columns' => [],
            ],
            'custom_rules' => [],
            'exit_codes' => [
                'warning_exit_code' => 0,
                'treat_warnings_as_failure' => false,
            ],
        ], $overrides);
    }
}
