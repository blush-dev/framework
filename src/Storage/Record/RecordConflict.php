<?php

/**
 * Record conflict.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage\Record;

use RuntimeException;

/**
 * Thrown when a save or a delete names the version it read (D-648) and
 * the record has changed since, or is gone.
 */
final class RecordConflict extends RuntimeException implements RecordException
{
	/**
	 * Refuses a write that names a version the stored record isn't at.
	 *
	 * @throws self
	 */
	public static function check(Table $table, string $id, ?Record $stored, ?string $version): void
	{
		if ($version === null || $stored?->version === $version) {
			return;
		}

		throw new self($stored === null
			? sprintf('The record %s in "%s" is gone.', $id, $table->name)
			: sprintf('The record %s in "%s" changed since it was read.', $id, $table->name));
	}
}
