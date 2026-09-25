<?php

/**
 * Text exception renderer.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Error;

use Override;
use Throwable;

/**
 * Renders an uncaught exception as plain text, for the CLI. With `debug` on,
 * traces are included.
 */
final readonly class TextRenderer implements ExceptionRenderer
{
	public function __construct(private bool $debug = false)
	{
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function contentType(): string
	{
		return 'text/plain; charset=UTF-8';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function render(Throwable $exception): string
	{
		$lines = [];

		do {
			$lines[] = sprintf(
				'%s%s: %s',
				$lines === [] ? '' : 'Caused by ',
				$exception::class,
				$exception->getMessage()
			);
			$lines[] = sprintf('  at %s:%d', $exception->getFile(), $exception->getLine());

			if ($this->debug) {
				$lines[] = $exception instanceof FatalError
					? $exception->fatalTraceAsString()
					: $exception->getTraceAsString();
			}

			$exception = $exception->getPrevious();
		} while ($exception !== null);

		return implode("\n", $lines) . "\n";
	}
}
