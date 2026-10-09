<?php

/**
 * SQL dialect.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage\Sql;

use Blush\Storage\Record\Table;

/**
 * What one database's SQL says differently, for `SqlCompiler` (D-640):
 * reading a value and its JSON type from a record's `fields`, a list
 * bound as one JSON value, a list's elements, text folded for case, and
 * a table's schema (D-644). Kept apart from the connection it runs on,
 * so a hosted variant can reuse it.
 *
 * Every type expression answers one of `null`, `true`, `false`,
 * `integer`, `real`, `text`, `array`, or `object`, or SQL's `NULL` for
 * a missing value; the compiler compares by type, never coercing (D-648).
 */
interface SqlDialect
{
	/**
	 * Returns an identifier quoted.
	 */
	public function quote(string $identifier): string;

	/**
	 * Returns a text value as an SQL literal.
	 */
	public function literal(string $text): string;

	/**
	 * Returns the expressions for a key's JSON type and value in a
	 * table's records: `id`, `content`, a field, or a nested field
	 * (dots between keys; a field whose own name has dots wins).
	 *
	 * @return array{string, string}
	 */
	public function read(Table $table, string $key): array;

	/**
	 * Returns a subselect of the values in a list bound as one JSON
	 * value (`?`).
	 */
	public function listed(): string;

	/**
	 * Returns a source of the elements of a key's value in a table's
	 * records, as rows with `key`, `type`, and `value` columns; a list's
	 * elements are the rows whose `key` is an integer.
	 */
	public function elements(Table $table, string $key): string;

	/**
	 * Returns text lowercased as `mb_strtolower()` does.
	 */
	public function fold(string $text): string;

	/**
	 * Returns whether text matches a `like` pattern bound as `?`, as
	 * `ArrayEvaluator` matches it (case-insensitive, `\` escaping).
	 */
	public function like(string $text): string;

	/**
	 * Returns a list's or map's JSON as PHP encodes it, for ordering.
	 */
	public function json(string $value): string;

	/**
	 * Returns the expression for the order records were added in.
	 */
	public function added(): string;

	/**
	 * Returns a table's name in the database: its area and name.
	 */
	public function table(Table $table): string;

	/**
	 * Returns the statements that create a table and index the values
	 * it declares, if they don't exist.
	 *
	 * @param  list<string> $columns The generated columns it already has.
	 * @return list<string>
	 */
	public function schema(Table $table, array $columns): array;
}
