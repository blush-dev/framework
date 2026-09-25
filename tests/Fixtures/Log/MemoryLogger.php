<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Log;

use Override;
use Stringable;
use Psr\Log\AbstractLogger;

/**
 * Keeps log records in memory.
 */
final class MemoryLogger extends AbstractLogger
{
	/** @var list<array{level: mixed, message: string, context: array<array-key, mixed>}> */
	public array $records = [];

	/**
	 * @param array<array-key, mixed> $context
	 */
	#[Override]
	public function log(mixed $level, string|Stringable $message, array $context = []): void
	{
		$this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
	}
}
