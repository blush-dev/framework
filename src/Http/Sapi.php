<?php

/**
 * SAPI output interface.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Http;

/**
 * The server API calls the emitter needs, behind an interface so emitting can
 * be tested without sending real headers.
 */
interface Sapi
{
	/**
	 * Whether headers have already been sent.
	 */
	public function headersSent(): bool;

	/**
	 * Sends a header line. `$replace` replaces an earlier header of the same
	 * name; `$status` sets the response code when non-zero.
	 */
	public function header(string $line, bool $replace = true, int $status = 0): void;

	/**
	 * Writes body output.
	 */
	public function write(string $data): void;

	/**
	 * Flushes the response to the client and, where the SAPI supports it,
	 * ends the request so deferred work can continue afterward.
	 */
	public function finish(): void;
}
