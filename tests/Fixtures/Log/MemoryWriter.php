<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Log;

use Override;
use Blush\Log\Level;
use Blush\Log\LogWriter;

/**
 * Keeps written log lines in memory.
 */
final class MemoryWriter implements LogWriter
{
	/** @var list<string> */
	public array $lines = [];

	#[Override]
	public function write(Level $level, string $line): void
	{
		$this->lines[] = $line;
	}
}
