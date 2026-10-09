<?php

/**
 * SQLite record store.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage\Sql;

use Closure;
use JsonException;
use Override;
use PDO;
use PDOException;
use PDOStatement;
use Pdo\Sqlite;
use Throwable;
use Blush\Storage\Record\Aggregate;
use Blush\Storage\Record\ArrayEvaluator;
use Blush\Storage\Record\InvalidRecord;
use Blush\Storage\Record\LocatingStore;
use Blush\Storage\Record\Record;
use Blush\Storage\Record\RecordConflict;
use Blush\Storage\Record\RecordQuery;
use Blush\Storage\Record\RecordResult;
use Blush\Storage\Record\RecordStore;
use Blush\Storage\Record\RecordStoreFailure;
use Blush\Storage\Record\SchemaStore;
use Blush\Storage\Record\Ref;
use Blush\Storage\Record\Related;
use Blush\Storage\Record\Subquery;
use Blush\Storage\Record\Table;
use Blush\Storage\StorageConfig;

/**
 * Keeps records in a SQLite database (D-606, D-640): a table each, its
 * fields as JSON, each value it declares a generated column with an
 * index (D-644), made the first time the table is used. Queries are SQL
 * (`SqlCompiler`); counts by value and aggregates reduce the matching
 * values as `ArrayEvaluator` does, so every store counts alike.
 *
 * The SQLite storage driver (step 5) will keep a site's data in one.
 *
 * @phpstan-import-type Row from ArrayEvaluator
 */
final class SqliteRecordStore implements RecordStore, SchemaStore, LocatingStore
{
	/**
	 * The tables made so far, by name in the database.
	 *
	 * @var array<string, true>
	 */
	private array $tables = [];

	/**
	 * How deep the transactions open now are.
	 */
	private int $depth = 0;

	private readonly SqliteDialect $dialect;

	private readonly SqlCompiler $compiler;

	public function __construct(
		private readonly Sqlite $pdo
	) {
		$this->dialect  = new SqliteDialect();
		$this->compiler = new SqlCompiler($this->dialect);
	}

	/**
	 * Opens the store a site keeps its records in: the database file
	 * `StorageConfig::$sqlite` names, from the site's root unless it's
	 * absolute, its folder made when missing (D-662).
	 *
	 * @throws RecordStoreFailure
	 */
	public static function forSite(StorageConfig $config, string $root): self
	{
		$file = str_starts_with($config->sqlite, '/') ? $config->sqlite : "{$root}/{$config->sqlite}";

		if (! is_dir(dirname($file)) && ! @mkdir(dirname($file), 0o755, true) && ! is_dir(dirname($file))) {
			throw new RecordStoreFailure(sprintf('The SQLite database\'s folder, %s, couldn\'t be made.', dirname($file)));
		}

		return self::open($file);
	}

	/**
	 * Opens a store on a database file, made if it doesn't exist, or
	 * `:memory:`.
	 *
	 * @throws RecordStoreFailure
	 */
	public static function open(string $path): self
	{
		return new self(SqliteConnection::open($path));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function prepare(Table $table): void
	{
		$this->table($table);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function analyze(): void
	{
		$this->run('ANALYZE');
	}

	/**
	 * Keeps the statistics queries plan by up to date as the connection
	 * closes, as SQLite advises for short-lived connections: it gathers
	 * them only for tables that changed enough to need it (D-667).
	 */
	public function __destruct()
	{
		try {
			$this->pdo->exec('PRAGMA optimize');
		} catch (Throwable) {
			// A connection that can't be optimized still closed fine.
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function find(Table $table, string $id): ?Record
	{
		return $this->first($table, 'id = ?', [strtolower($id)]);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function findByKey(Table $table, string $key): ?Record
	{
		if ($table->key === null) {
			throw new InvalidRecord(sprintf('"%s" has no key; find its records by id.', $table->name));
		}

		[$type, $value] = $this->dialect->read($table, $table->key);

		return $this->first($table, "{$type} IS 'text' AND {$value} = ?", [$key]);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function findMany(Table $table, array $ids): array
	{
		if ($ids === []) {
			return [];
		}

		$byId = [];

		foreach ($this->rows($table, "id IN {$this->dialect->listed()}", [self::encode(array_map(strtolower(...), $ids))], true) as $row) {
			$byId[$row['id']] = ArrayEvaluator::record($row);
		}

		$found = [];

		foreach ($ids as $id) {
			$record = $byId[strtolower($id)] ?? null;

			if ($record !== null) {
				$found[$record->id] = $record;
			}
		}

		return $found;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function save(Table $table, Record $record, ?string $version = null): Record
	{
		RecordConflict::check($table, $record->id, $this->find($table, $record->id), $version);

		$key = $table->keyOf($record);

		if ($key !== null) {
			$other = $this->findByKey($table, $key);

			$table->checkKey($record, $other === null ? [] : [$other]);
		}

		$stored = $record->withVersion(hash('xxh128', serialize([$record->fields, $record->content])));

		$this->run(
			"INSERT INTO {$this->table($table)} (id, fields, content, version, dotted) VALUES (?, ?, ?, ?, ?) ON CONFLICT (id) DO UPDATE SET fields = excluded.fields, content = excluded.content, version = excluded.version, dotted = excluded.dotted",
			[$stored->id, self::encode($stored->fields), $stored->content, $stored->version, self::dotted($stored) ? 1 : 0]
		);

		return $stored;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function delete(Table $table, string $id, ?string $version = null): void
	{
		RecordConflict::check($table, $id, $this->find($table, $id), $version);

		$this->run("DELETE FROM {$this->table($table)} WHERE id = ?", [strtolower($id)]);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function select(Table $table, RecordQuery $query): RecordResult
	{
		if ($query->only !== null) {
			return $this->projected($table, $query, $query->only);
		}

		[$rows, $total] = $this->matches($table, $query, $query->content ? 'id, fields, content, version' : 'id, fields, version');

		return new RecordResult(array_map(fn (array $row): Record => ArrayEvaluator::record($this->decoded($table, $row), $query->content), $rows), $total);
	}

	/**
	 * Returns the records a query finds with only some fields, read from
	 * their columns when the table declares them, else from the JSON, so
	 * no record's JSON is decoded whole.
	 *
	 * @param list<string> $keys
	 */
	private function projected(Table $table, RecordQuery $query, array $keys): RecordResult
	{
		$columns = ['id', 'version'];

		foreach ($keys as $index => $key) {
			[$type, $value] = $this->dialect->read($table, $key);
			$columns[]      = "{$type} AS t{$index}";
			$columns[]      = "{$value} AS v{$index}";
		}

		[$rows, $total] = $this->matches($table, $query, implode(', ', $columns));
		$records        = [];

		foreach ($rows as $row) {
			$fields = [];

			foreach ($keys as $index => $key) {
				$type  = $row["t{$index}"] ?? null;
				$value = $row["v{$index}"] ?? null;

				if (! is_string($type) || $type === 'null') {
					continue;
				}

				$fields[$key] = match ($type) {
					'true'           => true,
					'false'          => false,
					'integer'        => is_numeric($value) ? (int) $value : $value,
					'real'           => is_numeric($value) ? (float) $value : $value,
					'array', 'object' => is_string($value) ? json_decode($value, true) : $value,
					default          => $value
				};
			}

			if (is_string($row['id'] ?? null)) {
				$records[] = new Record($row['id'], $fields, null, is_string($row['version'] ?? null) ? $row['version'] : null);
			}
		}

		return new RecordResult($records, $total);
	}

	/**
	 * Returns the raw rows a query finds, with the columns asked for, in
	 * order, within its limit and offset, and how many it finds in all.
	 * A page's total is counted again, which is quick for conditions on
	 * columns, or, for costly ones, with the rows (and again only for a
	 * page past the end).
	 *
	 * @return array{list<array<string, mixed>>, int}
	 */
	private function matches(Table $table, RecordQuery $query, string $columns): array
	{
		$where  = $this->where($table, $query);
		$paged  = $query->limit !== null || $query->offset > 0;
		$window = $paged && $where->costly;
		$sql    = "SELECT {$columns}" . ($window ? ', COUNT(*) OVER () AS total' : '') . " FROM {$this->table($table)} WHERE {$where->sql} ORDER BY {$this->compiler->orderBy($table, $query->sorts)}";

		if ($paged) {
			$sql .= ' LIMIT ' . ($query->limit ?? -1) . ' OFFSET ' . $query->offset;
		}

		/** @var list<array<string, mixed>> $rows */
		$rows  = array_values(array_filter($this->run($sql, $where->params)->fetchAll(PDO::FETCH_ASSOC), is_array(...)));
		$total = match (true) {
			! $paged                                  => count($rows),
			$window && is_numeric($rows[0]['total'] ?? null) => (int) $rows[0]['total'],
			default                                   => $this->counted($table, $where)
		};

		return [$rows, $total];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function count(Table $table, RecordQuery $query): int
	{
		return $this->counted($table, $this->where($table, $query));
	}

	/**
	 * Returns how many records meet conditions.
	 */
	private function counted(Table $table, SqlFragment $where): int
	{
		$count = $this->run("SELECT COUNT(*) FROM {$this->table($table)} WHERE {$where->sql}", $where->params)->fetchColumn();

		return is_numeric($count) ? (int) $count : 0;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function countBy(Table $table, RecordQuery $query, string $key): array
	{
		RecordQuery::checkKey($key);

		return ArrayEvaluator::counts($this->values($table, $query, $key));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function aggregate(Table $table, RecordQuery $query, Aggregate $function, string $key): int|float|string|bool|null
	{
		RecordQuery::checkKey($key);

		return ArrayEvaluator::reduce($function, $this->values($table, $query, $key));
	}

	/**
	 * Runs writes in a transaction, nested ones as savepoints, rolled
	 * back when the closure throws.
	 *
	 * @inheritDoc
	 */
	#[Override]
	public function transaction(Closure $write): mixed
	{
		$savepoint = 'blush_' . ++$this->depth;

		$this->run("SAVEPOINT {$savepoint}");

		try {
			$result = $write();
		} catch (Throwable $error) {
			$this->run("ROLLBACK TO {$savepoint}");
			$this->run("RELEASE {$savepoint}");
			$this->depth--;

			throw $error;
		}

		$this->run("RELEASE {$savepoint}");
		$this->depth--;

		return $result;
	}

	/**
	 * Returns a query's conditions, with its subqueries and related
	 * conditions worked out.
	 */
	private function where(Table $table, RecordQuery $query): SqlFragment
	{
		return $this->compiler->where(
			$table,
			$query->conditions,
			fn (Subquery $subquery): array => $this->subquery($table, $subquery),
			fn (Related $related): array => $this->related($table, $related)
		);
	}

	/**
	 * Returns a subquery's values: its key's in the records it finds,
	 * within its order, limit, and offset, plain values only.
	 *
	 * @return list<bool|int|float|string>
	 */
	private function subquery(Table $outer, Subquery $subquery): array
	{
		$table  = $subquery->table ?? $outer;
		$values = [];

		foreach ($this->select($table, $subquery->query)->records as $record) {
			$value = $record->value($subquery->key);

			if (is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
				$values[] = $value;
			}
		}

		return $values;
	}

	/**
	 * Returns the ids a related condition keeps: the records that refer,
	 * through the relation, to one of the targets (or, inverse, are
	 * referred to by one), from the area's `refs` table.
	 *
	 * @return list<string>
	 */
	private function related(Table $table, Related $related): array
	{
		$targets = $related->targets instanceof Subquery ? $this->subquery($table, $related->targets) : $related->targets;
		$wanted  = array_map(static fn (bool|int|float|string $id): string => strtolower((string) $id), $targets);

		if ($wanted === []) {
			return [];
		}

		$refs        = Ref::table($table->area);
		[$from, $to] = $related->inverse ? ['target_id', 'source_id'] : ['source_id', 'target_id'];
		[, $source]  = $this->dialect->read($refs, $from);
		[, $target]  = $this->dialect->read($refs, $to);
		[, $name]    = $this->dialect->read($refs, 'relation');
		$found       = $this->run(
			"SELECT DISTINCT lower({$source}) FROM {$this->table($refs)} WHERE {$name} = ? AND typeof({$source}) = 'text' AND lower({$target}) IN {$this->dialect->listed()}",
			[$related->relation, self::encode($wanted)]
		)->fetchAll(PDO::FETCH_COLUMN);

		return array_values(array_filter($found, is_string(...)));
	}

	/**
	 * Returns the matching records' values of a key.
	 *
	 * @return list<mixed>
	 */
	private function values(Table $table, RecordQuery $query, string $key): array
	{
		$where = $this->where($table, $query);

		return array_map(
			static fn (array $row): mixed => ArrayEvaluator::value($row, $key),
			$this->rows($table, "{$where->sql} ORDER BY {$this->dialect->added()}", $where->params, $key === 'content')
		);
	}

	/**
	 * Returns the first record a condition finds, in the order added.
	 *
	 * @param list<mixed> $params
	 */
	private function first(Table $table, string $where, array $params): ?Record
	{
		$row = $this->rows($table, "{$where} ORDER BY {$this->dialect->added()} LIMIT 1", $params, true)[0] ?? null;

		return $row === null ? null : ArrayEvaluator::record($row);
	}

	/**
	 * Returns a table's rows meeting a condition (and whatever follows
	 * it: order, limit).
	 *
	 * @param  list<mixed> $params
	 * @return list<Row>
	 */
	private function rows(Table $table, string $where, array $params, bool $content): array
	{
		$columns = $content ? 'id, fields, content, version' : 'id, fields, version';
		$rows    = [];

		foreach ($this->run("SELECT {$columns} FROM {$this->table($table)} WHERE {$where}", $params)->fetchAll(PDO::FETCH_ASSOC) as $row) {
			if (is_array($row)) {
				$rows[] = $this->decoded($table, $row);
			}
		}

		return $rows;
	}

	/**
	 * Returns a raw row as a record's row, its fields decoded.
	 *
	 * @param  array<array-key, mixed> $row
	 * @return Row
	 * @throws RecordStoreFailure
	 */
	private function decoded(Table $table, array $row): array
	{
		try {
			$fields = json_decode(is_string($row['fields'] ?? null) ? $row['fields'] : '', true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			throw new RecordStoreFailure(sprintf('A record in "%s" can\'t be read: %s', $table->name, $e->getMessage()), 0, $e);
		}

		/** @var array<string, mixed> $fields */
		$fields = is_array($fields) ? $fields : [];

		return [
			'id'      => is_string($row['id'] ?? null) ? $row['id'] : '',
			'fields'  => $fields,
			'content' => is_string($row['content'] ?? null) ? $row['content'] : null,
			'version' => is_string($row['version'] ?? null) ? $row['version'] : null
		];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function location(Table $table, string $key): string
	{
		return sprintf('%s/%s in the database', $table->name, $key);
	}

	/**
	 * Returns a table's name, made in the database the first time.
	 */
	private function table(Table $table): string
	{
		$name = $this->dialect->table($table);

		if (! isset($this->tables[$name])) {
			$columns = $this->run("SELECT name FROM pragma_table_xinfo(?)", ["{$table->area->value}:{$table->name}"])->fetchAll(PDO::FETCH_COLUMN);

			foreach ($this->dialect->schema($table, array_values(array_filter($columns, is_string(...)))) as $statement) {
				$this->run($statement);
			}

			$this->tables[$name] = true;
		}

		return $name;
	}

	/**
	 * Runs a statement, integers bound as integers.
	 *
	 * @param  list<mixed> $params
	 * @throws RecordStoreFailure
	 */
	private function run(string $sql, array $params = []): PDOStatement
	{
		try {
			$statement = $this->pdo->prepare($sql);

			foreach ($params as $index => $param) {
				$statement->bindValue($index + 1, $param, match (true) {
					is_int($param)  => PDO::PARAM_INT,
					$param === null => PDO::PARAM_NULL,
					default         => PDO::PARAM_STR
				});
			}

			$statement->execute();
		} catch (PDOException $e) {
			throw new RecordStoreFailure(sprintf('The SQLite store couldn\'t run a query: %s', $e->getMessage()), 0, $e);
		}

		return $statement;
	}

	/**
	 * Returns whether a record has a field named with a dot.
	 */
	private static function dotted(Record $record): bool
	{
		return array_any(array_keys($record->fields), static fn (int|string $name): bool => str_contains((string) $name, '.'));
	}

	/**
	 * Returns a value as JSON, numbers kept as written.
	 */
	private static function encode(mixed $value): string
	{
		return json_encode($value, JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
	}
}
