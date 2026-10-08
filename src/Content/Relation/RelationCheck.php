<?php

/**
 * Relation check.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Relation;

/**
 * What replacing a relation's definition does to the entries using it
 * (`RelationChanges::check()`, D-600).
 */
final readonly class RelationCheck
{
	/**
	 * @param ?string      $refusal  Why it can't be made, or `null`.
	 * @param list<string> $warnings What it puts out of limits.
	 * @param int          $uses     How many entries have a value in it.
	 * @param int          $moved    How many entries a new key would move.
	 * @param list<string> $unfiled  The types no longer in its `from` whose entries have values.
	 * @param int          $stripped How many of their entries have values.
	 */
	public function __construct(
		public ?string $refusal = null,
		public array $warnings = [],
		public int $uses = 0,
		public int $moved = 0,
		public array $unfiled = [],
		public int $stripped = 0
	) {}

	/**
	 * Returns it as the admin's form reads it.
	 *
	 * @return array{refusal: ?string, warnings: list<string>, uses: int, moved: int, unfiled: list<string>, stripped: int}
	 */
	public function toArray(): array
	{
		return [
			'refusal'  => $this->refusal,
			'warnings' => $this->warnings,
			'uses'     => $this->uses,
			'moved'    => $this->moved,
			'unfiled'  => $this->unfiled,
			'stripped' => $this->stripped
		];
	}
}
