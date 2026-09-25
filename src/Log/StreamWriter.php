<?php

/**
 * Stream log writer.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Log;

use Override;

/**
 * Writes log lines to a stream, `php://stderr` by default, which suits the
 * CLI and containerized hosts that collect stderr.
 */
final class StreamWriter implements LogWriter
{
	/**
	 * The open stream, opened lazily on first write.
	 *
	 * @var ?resource
	 */
	private $stream = null;

	public function __construct(private readonly string $uri = 'php://stderr')
	{
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function write(Level $level, string $line): void
	{
		if ($this->stream === null) {
			$stream = @fopen($this->uri, 'a');

			if ($stream === false) {
				return;
			}

			$this->stream = $stream;
		}

		@fwrite($this->stream, $line . PHP_EOL);
	}
}
