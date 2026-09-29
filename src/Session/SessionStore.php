<?php

/**
 * Session store.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Session;

/**
 * Where sessions are kept between requests. A record is the session's
 * data plus when it started and when it was last used.
 */
interface SessionStore
{
	/**
	 * Returns a session's record, or `null` when there's none.
	 *
	 * @return ?array{created: int, lastSeen: int, data: array<string, mixed>}
	 */
	public function read(string $id): ?array;

	/**
	 * Saves a session's record.
	 *
	 * @param array{created: int, lastSeen: int, data: array<string, mixed>} $record
	 */
	public function write(string $id, array $record): void;

	/**
	 * Deletes a session.
	 */
	public function delete(string $id): void;

	/**
	 * Deletes every session last used before a time. Returns how many.
	 */
	public function prune(int $before): int;
}
