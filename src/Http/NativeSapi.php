<?php

/**
 * Native SAPI output.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Http;

use Override;

/**
 * Sends headers and output through PHP itself.
 */
final readonly class NativeSapi implements Sapi
{
	/**
	 * @inheritDoc
	 */
	#[Override]
	public function headersSent(): bool
	{
		return headers_sent();
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function header(string $line, bool $replace = true, int $status = 0): void
	{
		header($line, $replace, $status);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function write(string $data): void
	{
		echo $data;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function finish(): void
	{
		if (function_exists('fastcgi_finish_request')) {
			fastcgi_finish_request();
			return;
		}

		flush();
	}
}
