<?php

declare(strict_types=1);

namespace SchemaGuard\Migrations;

use RuntimeException;

final class NativeGitCommandRunner implements GitCommandRunner
{
    public function run(array $command, string $cwd): GitCommandResult
    {
        $process = proc_open(
            $command,
            [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $cwd,
        );

        if (! is_resource($process)) {
            throw new RuntimeException('Unable to start Git process.');
        }

        $output = stream_get_contents($pipes[1]) ?: '';
        fclose($pipes[1]);

        $error = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return new GitCommandResult($exitCode, $output, $error);
    }
}
