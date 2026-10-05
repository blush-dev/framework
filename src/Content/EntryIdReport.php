<?php

/**
 * Entry id report.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content;

/**
 * What `EntryIds::report()` found: the files missing a valid id, and the
 * files sharing each id held by more than one.
 */
final readonly class EntryIdReport
{
	/**
	 * @param list<string>                $missing    Paths, sorted.
	 * @param array<string, list<string>> $duplicates Paths by id.
	 */
	public function __construct(
		public array $missing = [],
		public array $duplicates = []
	) {}

	/**
	 * Returns whether every file has an id of its own.
	 */
	public function isClean(): bool
	{
		return $this->missing === [] && $this->duplicates === [];
	}
}
