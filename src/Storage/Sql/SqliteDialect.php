<?php

/**
 * SQLite dialect.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage\Sql;

use Override;
use Blush\Storage\Record\Table;

/**
 * SQLite's SQL, with its JSON functions (D-640): a record's `fields` is
 * JSON text, read with `json_extract()` and `json_type()`, and each value
 * a table declares has generated columns for its value (`f:{name}`,
 * indexed) and its JSON type (`t:{name}`), stored when the table is made,
 * so reading them parses no JSON (a value declared later gets virtual
 * ones, D-644). `dotted` says whether a record has a field named with a
 * dot, which a nested key looks for first. Case folding, `like`, and JSON for ordering are PHP
 * functions the connection registers (`SqliteConnection`), since
 * SQLite's own ignore case only in ASCII.
 */
final readonly class SqliteDialect implements SqlDialect
{
	/**
	 * The prefix of a declared value's generated column.
	 */
	public const string COLUMN = 'f:';

	/**
	 * The prefix of a declared value's type's generated column.
	 */
	public const string TYPE = 't:';

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function quote(string $identifier): string
	{
		return '"' . str_replace('"', '""', $identifier) . '"';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function literal(string $text): string
	{
		return "'" . str_replace("'", "''", $text) . "'";
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function read(Table $table, string $key): array
	{
		if ($key === 'id') {
			return ["'text'", 'id'];
		}

		if ($key === 'content') {
			return ["(CASE WHEN content IS NULL THEN NULL ELSE 'text' END)", 'content'];
		}

		$segments = explode('.', $key);

		// A key a JSON path can't name, or names two ways (`tags.0` is a
		// list's first value or a map's "0"), is read by PHP (`blush_get`).
		if (self::byPhp($key)) {
			$value = "blush_get(fields, {$this->literal($key)})";

			return ["json_type({$value})", "json_extract({$value}, '$')"];
		}

		$direct = $this->path([$key]);

		if (count($segments) === 1) {
			return self::declares($table, $key)
				? [$this->quote(self::TYPE . $key), $this->quote(self::COLUMN . $key)]
				: ["json_type(fields, {$direct})", "json_extract(fields, {$direct})"];
		}

		// A field named with dots wins, looked for only in records that
		// have one (`dotted`); the nested value is read from the declared
		// value it's in, when it's in one, so less JSON is parsed.
		$exists = "dotted = 1 AND json_type(fields, {$direct}) IS NOT NULL";
		[$source, $walk] = self::declares($table, $segments[0])
			? [$this->container($segments[0]), $this->path(array_slice($segments, 1))]
			: ['fields', $this->path($segments)];

		return [
			"(CASE WHEN {$exists} THEN json_type(fields, {$direct}) ELSE json_type({$source}, {$walk}) END)",
			"(CASE WHEN {$exists} THEN json_extract(fields, {$direct}) ELSE json_extract({$source}, {$walk}) END)"
		];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function listed(): string
	{
		return '(SELECT value FROM json_each(?))';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function elements(Table $table, string $key): string
	{
		$segments = explode('.', $key);

		if ($key === 'id' || $key === 'content') {
			return "json_each('null')";
		}

		if (self::byPhp($key)) {
			return "json_each(blush_get(fields, {$this->literal($key)}))";
		}

		$direct = $this->path([$key]);

		if (count($segments) === 1) {
			return self::declares($table, $key) ? "json_each({$this->container($key)})" : "json_each(fields, {$direct})";
		}

		$exists = "dotted = 1 AND json_type(fields, {$direct}) IS NOT NULL";
		[$source, $walk] = self::declares($table, $segments[0])
			? [$this->container($segments[0]), $this->path(array_slice($segments, 1))]
			: ['fields', $this->path($segments)];

		return "json_each(CASE WHEN {$exists} THEN fields ELSE {$source} END, CASE WHEN {$exists} THEN {$direct} ELSE {$walk} END)";
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function fold(string $text): string
	{
		return "blush_fold({$text})";
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function like(string $text): string
	{
		return "blush_like({$text}, ?)";
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function json(string $value): string
	{
		return "blush_json({$value})";
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function added(): string
	{
		return 'rowid';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function table(Table $table): string
	{
		return $this->quote("{$table->area->value}:{$table->name}");
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function schema(Table $table, array $columns): array
	{
		$name     = $this->table($table);
		$declared = array_values(array_filter(self::declared($table), static fn (string $field): bool => ! str_contains($field, '"')));
		$generate = fn (string $field, string $kind): array => [
			"{$this->quote(self::COLUMN . $field)} GENERATED ALWAYS AS (json_extract(fields, {$this->path([$field])})) {$kind}",
			"{$this->quote(self::TYPE . $field)} GENERATED ALWAYS AS (json_type(fields, {$this->path([$field])})) {$kind}"
		];

		// A new table stores its declared values; an older one gains them.
		if ($columns === []) {
			$stored     = implode('', array_map(static fn (string $field): string => ', ' . implode(', ', $generate($field, 'STORED')), $declared));
			$statements = ["CREATE TABLE IF NOT EXISTS {$name} (id TEXT PRIMARY KEY NOT NULL, fields TEXT NOT NULL, content TEXT, version TEXT, dotted INTEGER NOT NULL DEFAULT 0{$stored})"];
		} else {
			$statements = [];

			foreach ($declared as $field) {
				if (! in_array(self::COLUMN . $field, $columns, true)) {
					foreach ($generate($field, 'VIRTUAL') as $column) {
						$statements[] = "ALTER TABLE {$name} ADD COLUMN {$column}";
					}
				}
			}
		}

		foreach ($declared as $field) {
			$statements[] = "CREATE INDEX IF NOT EXISTS {$this->quote("{$table->area->value}:{$table->name}:{$field}")} ON {$name} ({$this->quote(self::COLUMN . $field)})";
		}

		return $statements;
	}

	/**
	 * Returns a declared value's column as JSON to read into: its JSON
	 * when it's a list or a map, else JSON's `null` (text in the column
	 * is the text itself, not JSON).
	 */
	private function container(string $field): string
	{
		$type = $this->quote(self::TYPE . $field);

		return "(CASE WHEN {$type} IS 'array' OR {$type} IS 'object' THEN {$this->quote(self::COLUMN . $field)} ELSE 'null' END)";
	}

	/**
	 * Returns a JSON path to nested keys, each quoted.
	 *
	 * @param list<string> $segments
	 */
	private function path(array $segments): string
	{
		return $this->literal('$' . implode('', array_map(static fn (string $segment): string => ".\"{$segment}\"", $segments)));
	}

	/**
	 * Returns whether a key is read by PHP: one a JSON path can't name
	 * (a `"`), or names two ways (a part that's a number).
	 */
	private static function byPhp(string $key): bool
	{
		return str_contains($key, '"') || preg_match('/(?:\A|\.)\d+(?:\.|\z)/', $key) === 1;
	}

	/**
	 * Returns the values a table declares, its key among them.
	 *
	 * @return list<string>
	 */
	private static function declared(Table $table): array
	{
		return array_values(array_unique([...$table->fields, ...($table->key === null ? [] : [$table->key])]));
	}

	/**
	 * Returns whether a table declares a value, so it has a column.
	 */
	private static function declares(Table $table, string $key): bool
	{
		return ! str_contains($key, '"') && in_array($key, self::declared($table), true);
	}
}
