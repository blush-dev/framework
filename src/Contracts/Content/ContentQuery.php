<?php
/**
 * Query interface.
 *
 * Defines the contract that content query classes should implement.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Contracts\Content;

interface ContentQuery
{
	/**
	 * Returns the located entries as an array.
	 *
	 * @since 1.0.0
	 */
	public function all(): array;

	/**
	 * Checks if the query has any entries.
	 *
	 * @since 1.0.0
	 */
	public function hasEntries(): bool;

	/**
	 * Checks if an entry was located by slug.
	 *
	 * @since 1.0.0
	 */
	public function has( string $slug ): bool;

	/**
	 * Returns the first entry. Alias for `first()`.
	 *
	 * @since 1.0.0
	 */
	public function single(): ?ContentEntry;

	/**
	 * Returns the first entry.
	 *
	 * @since 1.0.0
	 */
	public function first(): ?ContentEntry;

	/**
	 * Returns the last entry.
	 *
	 * @since 1.0.0
	 */
	public function last(): ?ContentEntry;

	/**
	 * Returns the count for the current query.
	 *
	 * @since 1.0.0
	 */
	public function count(): int;

	/**
	 * Returns the total entries.
	 *
	 * @since 1.0.0
	 */
	public function total(): int;

	/**
	 * Returns the number query option.
	 *
	 * @since 1.0.0
	 */
	public function number(): int;

	/**
	 * Returns the number of pages of entries.
	 *
	 * @since 1.0.0
	 */
	public function pages(): int;

	/**
	 * Returns the offset query option.
	 *
	 * @since 1.0.0
	 */
	public function offset(): int;
}
