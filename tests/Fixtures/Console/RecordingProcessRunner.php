<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Console;

use Override;
use Blush\Console\ProcessRunner;

final class RecordingProcessRunner implements ProcessRunner
{
	/**
	 * @var list<array{command: list<string>, cwd: ?string}>
	 */
	public array $runs = [];

	public function __construct(private int $exitCode = 0)
	{
	}

	#[Override]
	public function run(array $command, ?string $cwd = null): int
	{
		$this->runs[] = ['command' => $command, 'cwd' => $cwd];

		return $this->exitCode;
	}
}
