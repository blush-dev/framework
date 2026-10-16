<?php

/**
 * Restore check.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Backup;

use Stringable;

/**
 * What stands in the way of restoring parts of a backup (`problems`,
 * which refuse it) and what restoring them will do that's worth knowing
 * first (`warnings`), such as signing everyone out (D-886).
 */
final readonly class RestoreCheck
{
	/**
	 * @param list<string|Stringable> $problems
	 * @param list<string|Stringable> $warnings
	 */
	public function __construct(
		public array $problems = [],
		public array $warnings = []
	) {}

	/**
	 * Returns whether the restore can go ahead.
	 */
	public function allowed(): bool
	{
		return $this->problems === [];
	}
}
