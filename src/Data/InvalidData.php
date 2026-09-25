<?php

/**
 * Invalid data.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Data;

use RuntimeException;

/**
 * Thrown when a data file or string can't be read or parsed: malformed
 * JSON or YAML, a YAML tag that would build an object, or a top level that
 * isn't a map or list.
 */
final class InvalidData extends RuntimeException implements DataException
{
	/**
	 * Names the file a parse error came from.
	 */
	public static function inFile(string $path, InvalidData $previous): self
	{
		return new self(sprintf('%s: %s', $path, $previous->getMessage()), previous: $previous);
	}
}
