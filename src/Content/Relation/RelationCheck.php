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
 * (`RelationChanges::check()`, D-600), counted before it happens, with
 * the entries in the way named (D-610): up to `RelationCheck::LISTED`,
 * most values first.
 *
 * - `refused` says why it can't be made: `type` (it points at another
 *   type while entries have values) or `one` (it takes one where entries
 *   have several), with those entries in `inWay`.
 * - `over` and `overCount` are the entries with more values than a new
 *   `max`, which keep them and can't be published again until they're
 *   down to it.
 *
 * @phpstan-type CheckItem array{id: ?string, type: string, title: string, count: int}
 */
final readonly class RelationCheck
{
	/**
	 * How many entries a check names; the rest are counted.
	 */
	public const int LISTED = 8;

	/**
	 * @param ?string         $refusal     Why it can't be made, or `null`.
	 * @param list<string>    $warnings    What it puts out of limits.
	 * @param int             $uses        How many entries have a value in it.
	 * @param int             $moved       How many entries a new key would move.
	 * @param list<string>    $unfiled     The types no longer in its `from` whose entries have values.
	 * @param int             $stripped    How many of their entries have values.
	 * @param ?string         $refused     Why it's refused, by name: `type` or `one`.
	 * @param list<CheckItem> $inWay       The entries in the way of a refusal, most values first.
	 * @param int             $inWayCount  How many entries are in the way.
	 * @param list<CheckItem> $over        The entries over a new `max`, most values first.
	 * @param int             $overCount   How many entries are over it.
	 * @param int             $fewer       How many live entries have fewer than a new `min`.
	 * @param int             $inverseOver How many targets more entries name than a new inverse `max`.
	 */
	public function __construct(
		public ?string $refusal = null,
		public array $warnings = [],
		public int $uses = 0,
		public int $moved = 0,
		public array $unfiled = [],
		public int $stripped = 0,
		public ?string $refused = null,
		public array $inWay = [],
		public int $inWayCount = 0,
		public array $over = [],
		public int $overCount = 0,
		public int $fewer = 0,
		public int $inverseOver = 0
	) {}

	/**
	 * Returns it as the admin's form reads it.
	 *
	 * @return array{refusal: ?string, warnings: list<string>, uses: int, moved: int, unfiled: list<string>, stripped: int, refused: ?string, inWay: list<CheckItem>, inWayCount: int, over: list<CheckItem>, overCount: int, fewer: int, inverseOver: int}
	 */
	public function toArray(): array
	{
		return [
			'refusal'     => $this->refusal,
			'warnings'    => $this->warnings,
			'uses'        => $this->uses,
			'moved'       => $this->moved,
			'unfiled'     => $this->unfiled,
			'stripped'    => $this->stripped,
			'refused'     => $this->refused,
			'inWay'       => $this->inWay,
			'inWayCount'  => $this->inWayCount,
			'over'        => $this->over,
			'overCount'   => $this->overCount,
			'fewer'       => $this->fewer,
			'inverseOver' => $this->inverseOver
		];
	}
}
