<?php

/**
 * File record store.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage\File;

use Closure;
use DirectoryIterator;
use JsonException;
use Override;
use Throwable;
use Blush\Container\Attributes\Defer;
use Blush\Content\Index\IndexStore;
use Blush\Core\Paths;
use Blush\Storage\Record\Aggregate;
use Blush\Storage\Record\ArrayEvaluator;
use Blush\Storage\Record\InvalidRecord;
use Blush\Storage\Record\Record;
use Blush\Storage\Record\RecordConflict;
use Blush\Storage\Record\RecordQuery;
use Blush\Storage\Record\RecordResult;
use Blush\Storage\Record\RecordStore;
use Blush\Storage\Record\RecordStoreFailure;
use Blush\Storage\Record\Table;
use Blush\Storage\StorageException;
use Blush\Support\Filesystem;
use Blush\Support\Uuid;

/**
 * The filesystem driver's records (D-643): each table kept as its
 * `FileLayout` says, a folder of JSON files or one JSON file, a record
 * written as its fields, then its `content` when it has one, then its
 * `id`, last (D-477). Its version (D-648) is a hash of that.
 *
 * - **Order:** a one-file table's records come in the file's order, new
 *   ones added at the end; a folder's by id, which for version 7 ids is
 *   the order they were made.
 * - **Records without an id** (written before tables had them, D-646)
 *   get a steady one from their table and key (`Uuid::fromName()`), or
 *   their file's name when it's an id, until they're saved with it.
 * - **Queries** read the table and run through `ArrayEvaluator`; each
 *   call reads it again, so nothing goes stale in a long-running worker.
 *   It suits data-sized tables.
 * - **Content** (the content area's `entries` and `refs`, D-649) is the
 *   content store's, which keeps it as Markdown files with an index
 *   (`IndexStore`, D-653); this hands those tables to it.
 * - **Writes** are atomic, each in a transaction (`FileTransactions`,
 *   shared with `FileDataStore`), so a write that reads the table first
 *   can't lose another's.
 *
 * @phpstan-import-type Row from ArrayEvaluator
 */
final readonly class FileRecordStore implements RecordStore
{
	/**
	 * The extension of a record's file.
	 */
	public const string EXTENSION = 'json';

	/**
	 * @param Closure(): IndexStore $content
	 */
	public function __construct(
		private Paths $paths,
		private FileLayouts $layouts,
		private FileTransactions $transactions,
		private Filesystem $filesystem,
		#[Defer(IndexStore::class)] private Closure $content,
		private ArrayEvaluator $evaluator = new ArrayEvaluator()
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function find(Table $table, string $id): ?Record
	{
		if (IndexStore::keeps($table)) {
			return ($this->content)()->find($table, $id);
		}

		return $this->records($table)[strtolower($id)] ?? null;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function findByKey(Table $table, string $key): ?Record
	{
		if (IndexStore::keeps($table)) {
			return ($this->content)()->findByKey($table, $key);
		}

		if ($table->key === null) {
			throw new InvalidRecord(sprintf('"%s" has no key; find its records by id.', $table->name));
		}

		return array_find($this->records($table), static fn (Record $record): bool => ($record->fields[$table->key] ?? null) === $key);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function findMany(Table $table, array $ids): array
	{
		if (IndexStore::keeps($table)) {
			return ($this->content)()->findMany($table, $ids);
		}

		$records = $this->records($table);
		$found   = [];

		foreach ($ids as $id) {
			$record = $records[strtolower($id)] ?? null;

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
		if (IndexStore::keeps($table)) {
			return ($this->content)()->save($table, $record, $version);
		}

		return $this->transaction(function () use ($table, $record, $version): Record {
			$records = $this->records($table);
			$layout  = $this->layouts->for($table);
			$stored  = $record->withVersion(self::version(self::encode($record)));

			RecordConflict::check($table, $record->id, $records[$record->id] ?? null, $version);

			$table->checkKey($record, $records);

			if ($layout->oneFile) {
				$records[$record->id] = $stored;

				$this->writeOneFile($layout, $records);

				return $stored;
			}

			$path = $this->file($layout, $table, $record);
			$old  = $records[$record->id] ?? null;

			// A record whose key changed moves to its new name.
			if ($old !== null && $this->file($layout, $table, $old) !== $path) {
				$this->remove($this->file($layout, $table, $old));
			}

			$this->write($path, $this->json(self::encode($record)), $layout->mode);

			return $stored;
		});
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function delete(Table $table, string $id, ?string $version = null): void
	{
		if (IndexStore::keeps($table)) {
			($this->content)()->delete($table, $id, $version);

			return;
		}

		$this->transaction(function () use ($table, $id, $version): void {
			$records = $this->records($table);
			$record  = $records[strtolower($id)] ?? null;

			RecordConflict::check($table, $id, $record, $version);

			if ($record === null) {
				return;
			}

			$layout = $this->layouts->for($table);

			if ($layout->oneFile) {
				unset($records[$record->id]);

				$this->writeOneFile($layout, $records);

				return;
			}

			$this->remove($this->file($layout, $table, $record));
		});
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function select(Table $table, RecordQuery $query): RecordResult
	{
		if (IndexStore::keeps($table)) {
			return ($this->content)()->select($table, $query);
		}

		return $this->evaluator->select($table, $query, $this->list(...));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function count(Table $table, RecordQuery $query): int
	{
		if (IndexStore::keeps($table)) {
			return ($this->content)()->count($table, $query);
		}

		return $this->evaluator->count($table, $query, $this->list(...));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function countBy(Table $table, RecordQuery $query, string $key): array
	{
		if (IndexStore::keeps($table)) {
			return ($this->content)()->countBy($table, $query, $key);
		}

		return $this->evaluator->countBy($table, $query, $key, $this->list(...));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function aggregate(Table $table, RecordQuery $query, Aggregate $function, string $key): int|float|string|bool|null
	{
		if (IndexStore::keeps($table)) {
			return ($this->content)()->aggregate($table, $query, $function, $key);
		}

		return $this->evaluator->aggregate($table, $query, $function, $key, $this->list(...));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function transaction(Closure $write): mixed
	{
		try {
			return $this->transactions->run($write);
		} catch (StorageException $error) {
			throw new RecordStoreFailure($error->getMessage(), previous: $error);
		}
	}

	/**
	 * A table's records as rows, in order, for the evaluator.
	 *
	 * @return list<Row>
	 * @throws InvalidRecord
	 */
	private function list(Table $table): array
	{
		return array_values(array_map(ArrayEvaluator::row(...), $this->records($table)));
	}

	/**
	 * A table's records by id, in order.
	 *
	 * @return array<string, Record>
	 * @throws InvalidRecord When the table can't be read.
	 */
	private function records(Table $table): array
	{
		$layout = $this->layouts->for($table);

		return $layout->oneFile ? $this->readOneFile($table, $layout) : $this->readFolder($table, $layout);
	}

	/**
	 * Reads a one-file table.
	 *
	 * @return array<string, Record>
	 * @throws InvalidRecord
	 */
	private function readOneFile(Table $table, FileLayout $layout): array
	{
		$data     = $this->read($layout->path);
		$location = $this->paths->relative($layout->path);

		if ($data === null) {
			return [];
		}

		$list = $layout->root === null ? $data : ($data[$layout->root] ?? []);

		if (! is_array($list) || ! array_is_list($list)) {
			throw new InvalidRecord(sprintf('%s needs a list of records%s.', $location, $layout->root === null ? '' : " under \"{$layout->root}\""));
		}

		$records = [];

		foreach ($list as $item) {
			$record = $this->decode($table, is_array($item) ? $item : [], $location);

			if (isset($records[$record->id])) {
				throw new InvalidRecord(sprintf('%s has two records with the id %s.', $location, $record->id));
			}

			$records[$record->id] = $record;
		}

		return $records;
	}

	/**
	 * Reads a folder table, by id.
	 *
	 * @return array<string, Record>
	 * @throws InvalidRecord
	 */
	private function readFolder(Table $table, FileLayout $layout): array
	{
		if (! is_dir($layout->path)) {
			return [];
		}

		$records = [];

		foreach (new DirectoryIterator($layout->path) as $file) {
			if (! $file->isFile() || str_starts_with($file->getFilename(), '.') || strtolower($file->getExtension()) !== self::EXTENSION) {
				continue;
			}

			$name     = $file->getBasename('.' . $file->getExtension());
			$location = $this->paths->relative($file->getPathname());
			$record   = $this->decode($table, $this->read($file->getPathname()) ?? [], $location, $name);

			if (isset($records[$record->id])) {
				throw new InvalidRecord(sprintf('%s has the id of another record in %s.', $location, $this->paths->relative($layout->path)));
			}

			$records[$record->id] = $record;
		}

		ksort($records, SORT_STRING);

		return $records;
	}

	/**
	 * Reads a JSON file, or `null` when there's none.
	 *
	 * @return ?array<array-key, mixed>
	 * @throws InvalidRecord When it isn't a JSON object or list.
	 */
	private function read(string $path): ?array
	{
		if (! is_file($path)) {
			return null;
		}

		$text = @file_get_contents($path);

		try {
			$data = $text === false || trim($text) === '' ? [] : json_decode($text, true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException $error) {
			throw new InvalidRecord(sprintf('%s isn\'t valid JSON: %s', $this->paths->relative($path), $error->getMessage()), previous: $error);
		}

		if (! is_array($data)) {
			throw new InvalidRecord(sprintf('%s must hold a JSON object or list.', $this->paths->relative($path)));
		}

		return $data;
	}

	/**
	 * A record from what a file holds. A file named by its key fills in
	 * a missing key value.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws InvalidRecord
	 */
	private function decode(Table $table, array $data, string $location, ?string $name = null): Record
	{
		if ($data !== [] && array_is_list($data)) {
			throw new InvalidRecord(sprintf('%s has a record that isn\'t a JSON object.', $location));
		}

		$fields = array_diff_key($data, array_flip(Record::RESERVED));

		if ($table->key !== null && ! isset($fields[$table->key]) && $name !== null && ! Uuid::isValid($name)) {
			$fields[$table->key] = $name;
		}

		$key   = $table->key === null ? null : ($fields[$table->key] ?? null);
		$given = $data['id'] ?? null;
		$id    = match (true) {
			is_string($given) && Uuid::isValid($given)  => $given,
			is_string($key) && $key !== ''              => Uuid::fromName("{$table->name}/{$key}"),
			$name !== null && Uuid::isValid($name)      => $name,
			default                                     => throw new InvalidRecord(sprintf('%s has a record with no id%s.', $location, $table->key === null ? '' : " or \"{$table->key}\""))
		};

		/** @var array<string, mixed> $fields */
		return new Record($id, $fields, is_string($data['content'] ?? null) ? $data['content'] : null, self::version($data));
	}

	/**
	 * What a record's file, or its place in a one-file table, holds.
	 *
	 * @return array<string, mixed>
	 */
	private static function encode(Record $record): array
	{
		return [...$record->fields, ...($record->content === null ? [] : ['content' => $record->content]), 'id' => $record->id];
	}

	/**
	 * A record's version (D-648): a hash of what its file, or its place
	 * in a one-file table, holds.
	 *
	 * @param array<array-key, mixed> $data
	 */
	private static function version(array $data): string
	{
		return hash('xxh128', (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
	}

	/**
	 * Writes a one-file table, keeping its other top-level keys.
	 *
	 * @param  array<string, Record> $records
	 * @throws InvalidRecord|RecordStoreFailure
	 */
	private function writeOneFile(FileLayout $layout, array $records): void
	{
		$list = array_values(array_map(self::encode(...), $records));
		$data = $layout->root === null ? $list : [...($this->read($layout->path) ?? []), $layout->root => $list];

		$this->write($layout->path, $this->json($data), $layout->mode);
	}

	/**
	 * A record's file in a folder table: its key, or its id.
	 */
	private function file(FileLayout $layout, Table $table, Record $record): string
	{
		return "{$layout->path}/" . ($table->keyOf($record) ?? $record->id) . '.' . self::EXTENSION;
	}

	/**
	 * Data as the files hold it.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws RecordStoreFailure When it can't be written as JSON.
	 */
	private function json(array $data): string
	{
		try {
			return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
		} catch (JsonException $error) {
			throw new RecordStoreFailure(sprintf('The record can\'t be written as JSON: %s', $error->getMessage()), previous: $error);
		}
	}

	/**
	 * Writes a file in the open transaction.
	 *
	 * @throws RecordStoreFailure
	 */
	private function write(string $path, string $text, int $mode): void
	{
		$this->transactions->remember($path);

		try {
			$this->filesystem->writeAtomic($path, $text, $mode);
		} catch (Throwable $error) {
			throw new RecordStoreFailure(sprintf('Unable to write %s.', $this->paths->relative($path)), previous: $error);
		}
	}

	/**
	 * Removes a file in the open transaction.
	 *
	 * @throws RecordStoreFailure
	 */
	private function remove(string $path): void
	{
		if (! is_file($path)) {
			return;
		}

		$this->transactions->remember($path);

		if (! @unlink($path)) {
			throw new RecordStoreFailure(sprintf('Unable to delete %s.', $this->paths->relative($path)));
		}
	}
}
