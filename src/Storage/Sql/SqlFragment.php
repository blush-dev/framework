<?php

/**
 * SQL fragment.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage\Sql;

/**
 * A piece of SQL and the values bound to its placeholders (`?`), in
 * order. Every condition the compiler builds is true or false, never
 * `NULL`, so groups and `NOT` combine them safely. `$costly` marks a
 * condition that reads into each record's values (a list's elements),
 * so a store works it out once a query rather than again to count.
 */
final readonly class SqlFragment
{
	/**
	 * @param list<mixed> $params
	 */
	public function __construct(
		public string $sql,
		public array $params = [],
		public bool $costly = false
	) {}

	/**
	 * Returns fragments joined by `AND` (true for none) or `OR` (false
	 * for none).
	 *
	 * @param list<self> $fragments
	 */
	public static function join(array $fragments, bool $all): self
	{
		if ($fragments === []) {
			return new self($all ? '1' : '0');
		}

		if (count($fragments) === 1) {
			return $fragments[0];
		}

		return new self(
			'(' . implode($all ? ' AND ' : ' OR ', array_map(static fn (self $fragment): string => $fragment->sql, $fragments)) . ')',
			array_merge(...array_map(static fn (self $fragment): array => $fragment->params, $fragments)),
			array_any($fragments, static fn (self $fragment): bool => $fragment->costly)
		);
	}

	/**
	 * Returns the fragment negated.
	 */
	public function not(): self
	{
		return new self("(NOT {$this->sql})", $this->params, $this->costly);
	}
}
