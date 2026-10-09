<?php

/**
 * Media files.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media;

use Closure;
use FilesystemIterator;
use JsonException;
use Override;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;
use Blush\Core\Paths;
use Blush\Storage\File\FileTransactions;
use Blush\Storage\Record\Aggregate;
use Blush\Storage\Record\ArrayEvaluator;
use Blush\Storage\Record\Condition;
use Blush\Storage\Record\InvalidRecord;
use Blush\Storage\Record\Junction;
use Blush\Storage\Record\LocatingStore;
use Blush\Storage\Record\Operator;
use Blush\Storage\Record\Record;
use Blush\Storage\Record\RecordConflict;
use Blush\Storage\Record\RecordQuery;
use Blush\Storage\Record\RecordResult;
use Blush\Storage\Record\RecordStore;
use Blush\Storage\Record\RecordStoreFailure;
use Blush\Storage\Record\Table;
use Blush\Storage\StorageArea;
use Blush\Storage\StorageException;
use Blush\Support\Filesystem;
use Blush\Support\Uuid;

/**
 * The filesystem driver's media table (D-674, D-675): the metadata files
 * as they've always been, `user/data/media/{path}.json`, one for each
 * media original that has any, nested as the originals are. A record's
 * `path` is where its file is, never written in it; its description is
 * the file's `content`; its `id` is written last.
 *
 * - **What isn't a record:** a file that isn't JSON or a map, one without
 *   a valid id, and every file sharing an id with another, until Media
 *   IDs gives each its own (as D-656 for content). `content:lint` and
 *   Site Health report them, reading the files as they are (`raw()`).
 * - **Reading:** a query that names its paths (`path` equal to one, or in
 *   a list) reads only those files; any other reads every file.
 * - **Changes** are told by the files' times (`stamps()`), for the media
 *   index, without reading them.
 *
 * `FileRecordStore` hands the table to this store, as it hands content to
 * the content store.
 *
 * @phpstan-import-type Row from ArrayEvaluator
 */
final readonly class MediaFiles implements RecordStore, LocatingStore
{
	/**
	 * The key pointing an editor at a file's JSON Schema (D-491).
	 */
	private const string SCHEMA = '$schema';

	public function __construct(
		private Paths $paths,
		private FileTransactions $transactions,
		private Filesystem $filesystem = new Filesystem(),
		private ArrayEvaluator $evaluator = new ArrayEvaluator()
	) {}

	/**
	 * Whether a table is the one this store keeps.
	 */
	public static function keeps(Table $table): bool
	{
		return $table->name === MediaMetadataStore::TABLE && $table->area === StorageArea::Data;
	}

	/**
	 * The folder the metadata files are in.
	 */
	public function root(): string
	{
		return "{$this->paths->data}/" . MediaMetadataStore::FOLDER;
	}

	/**
	 * Every metadata file, by the path of the original it describes, with
	 * when it last changed, whether it's a record or not: what the media
	 * index goes by (D-288). They aren't read.
	 *
	 * @return array<string, int>
	 */
	public function stamps(): array
	{
		$stamps = [];

		foreach ($this->files() as $path => $file) {
			$stamps[$path] = (int) $file->getMTime();
		}

		ksort($stamps, SORT_STRING);

		return $stamps;
	}

	/**
	 * What a path's metadata file holds as it is, records or not, for
	 * checking it; `null` when there's none.
	 *
	 * @return ?array<array-key, mixed>
	 * @throws InvalidRecord When it isn't valid JSON or a JSON object.
	 */
	public function raw(string $path): ?array
	{
		$file = $this->file($path);

		if (! is_file($file)) {
			return null;
		}

		$text = (string) @file_get_contents($file);

		try {
			$data = trim($text) === '' ? [] : json_decode($text, true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException $error) {
			throw new InvalidRecord(sprintf('%s isn\'t valid JSON: %s', $this->paths->relative($file), $error->getMessage()), previous: $error);
		}

		return is_array($data) ? $data : throw new InvalidRecord(sprintf('%s must hold a JSON object.', $this->paths->relative($file)));
	}

	/**
	 * Removes a path's metadata file, a record or not, in a transaction.
	 *
	 * @throws RecordStoreFailure When it can't be removed.
	 */
	public function discard(string $path): void
	{
		$path = trim($path, '/');

		if ($path === '' || str_contains($path, '..')) {
			return;
		}

		$this->transaction(fn () => $this->remove($this->file($path)));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function find(Table $table, string $id): ?Record
	{
		return $this->records()[strtolower($id)] ?? null;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function findByKey(Table $table, string $key): ?Record
	{
		throw new InvalidRecord(sprintf('"%s" has no key; find its records by id, or by path with a query.', $table->name));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function findMany(Table $table, array $ids): array
	{
		$records = $this->records();
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
		return $this->transaction(function () use ($table, $record, $version): Record {
			// A path has one file, so a record saved at a path replaces
			// what was there, as Media IDs does to give a file its own id.
			$path = self::pathOf($record);
			$old  = $this->records()[$record->id] ?? null;

			RecordConflict::check($table, $record->id, $old, $version);

			// A record whose path changed moves its file.
			if ($old !== null && self::pathOf($old) !== $path) {
				$this->remove($this->file(self::pathOf($old)));
			}

			$file    = $this->file($path);
			$written = [
				...self::schema($this->read($file)),
				...array_diff_key($record->fields, ['path' => true]),
				...($record->content === null ? [] : [MediaMetadata::CONTENT => $record->content]),
				MediaMetadata::ID => $record->id
			];

			$this->write($file, $written);

			return $record->withVersion(self::version($written));
		});
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function delete(Table $table, string $id, ?string $version = null): void
	{
		$this->transaction(function () use ($table, $id, $version): void {
			$record = $this->records()[strtolower($id)] ?? null;

			RecordConflict::check($table, $id, $record, $version);

			if ($record !== null) {
				$this->remove($this->file(self::pathOf($record)));
			}
		});
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function select(Table $table, RecordQuery $query): RecordResult
	{
		return $this->evaluator->select($table, $query, $this->rows($query));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function count(Table $table, RecordQuery $query): int
	{
		return $this->evaluator->count($table, $query, $this->rows($query));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function countBy(Table $table, RecordQuery $query, string $key): array
	{
		return $this->evaluator->countBy($table, $query, $key, $this->rows($query));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function aggregate(Table $table, RecordQuery $query, Aggregate $function, string $key): int|float|string|bool|null
	{
		return $this->evaluator->aggregate($table, $query, $function, $key, $this->rows($query));
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
	 * Where a record is kept, by its path, or its id when it has a file.
	 */
	#[Override]
	public function location(Table $table, string $key): string
	{
		$record = Uuid::isValid($key) ? ($this->records()[strtolower($key)] ?? null) : null;

		return $this->paths->relative($this->file($record === null ? $key : self::pathOf($record)));
	}

	/**
	 * The rows a query reads: only the files it names by path, when it
	 * names them, else every record.
	 *
	 * @return Closure(Table): list<Row>
	 */
	private function rows(RecordQuery $query): Closure
	{
		$paths = self::paths($query);

		return function () use ($paths): array {
			$records = $paths === null ? $this->records() : array_filter(array_map($this->recordAt(...), $paths));

			return array_values(array_map(ArrayEvaluator::row(...), $records));
		};
	}

	/**
	 * The paths a query names, when every record it finds must be at one:
	 * a top-level `path` equal to one, or in a list.
	 *
	 * @return ?list<string>
	 */
	private static function paths(RecordQuery $query): ?array
	{
		if ($query->conditions->junction !== Junction::All) {
			return null;
		}

		foreach ($query->conditions->conditions as $condition) {
			if (! $condition instanceof Condition || $condition->key !== 'path') {
				continue;
			}

			if ($condition->operator === Operator::Equal && is_string($condition->value)) {
				return [$condition->value];
			}

			if ($condition->operator === Operator::In && is_array($condition->value)) {
				return array_values(array_filter($condition->value, is_string(...)));
			}
		}

		return null;
	}

	/**
	 * Every record, by id: the files with a valid id no other file shares.
	 *
	 * @return array<string, Record>
	 */
	private function records(): array
	{
		$byId = [];

		foreach ($this->files() as $path => $file) {
			$record = $this->decode($path, $file->getPathname());

			if ($record !== null) {
				$byId[$record->id][] = $record;
			}
		}

		$records = [];

		foreach ($byId as $id => $found) {
			if (count($found) === 1) {
				$records[(string) $id] = $found[0];
			}
		}

		ksort($records, SORT_STRING);

		return $records;
	}

	/**
	 * The record at a path, or `null` when its file isn't one. A file
	 * sharing its id with another found this way is still the record at
	 * its path; `records()` leaves both out.
	 */
	private function recordAt(string $path): ?Record
	{
		$path = trim($path, '/');
		$file = $this->file($path);

		return $path === '' || str_contains($path, '..') || ! is_file($file) ? null : $this->decode($path, $file);
	}

	/**
	 * A record from a metadata file, or `null` when it isn't one.
	 */
	private function decode(string $path, string $file): ?Record
	{
		$data = $this->read($file);
		$id   = $data[MediaMetadata::ID] ?? null;

		if ($data === null || ! is_string($id) || ! Uuid::isValid($id)) {
			return null;
		}

		$fields = array_diff_key($data, [MediaMetadata::ID => true, MediaMetadata::CONTENT => true, self::SCHEMA => true, 'path' => true]);

		/** @var array<string, mixed> $fields */
		return new Record(strtolower($id), [...$fields, 'path' => $path], is_string($data[MediaMetadata::CONTENT] ?? null) ? $data[MediaMetadata::CONTENT] : null, self::version($data));
	}

	/**
	 * What a metadata file holds, or `null` when it isn't a JSON object.
	 *
	 * @return ?array<array-key, mixed>
	 */
	private function read(string $file): ?array
	{
		$text = @file_get_contents($file);

		if ($text === false) {
			return null;
		}

		try {
			$data = trim($text) === '' ? [] : json_decode($text, true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			return null;
		}

		return is_array($data) && ($data === [] || ! array_is_list($data)) ? $data : null;
	}

	/**
	 * Every metadata file, by the path of the original it describes.
	 *
	 * @return array<string, SplFileInfo>
	 */
	private function files(): array
	{
		$root = $this->root();

		if (! is_dir($root)) {
			return [];
		}

		$files    = [];
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

		foreach ($iterator as $file) {
			if ($file instanceof SplFileInfo && $file->isFile() && strtolower($file->getExtension()) === 'json' && ! str_starts_with($file->getFilename(), '.')) {
				$relative = substr($file->getPathname(), strlen($root) + 1, -5);

				$files[str_replace('\\', '/', $relative)] = $file;
			}
		}

		return $files;
	}

	/**
	 * A path's metadata file.
	 */
	private function file(string $path): string
	{
		return $this->root() . '/' . trim($path, '/') . '.json';
	}

	/**
	 * A record's path.
	 *
	 * @throws InvalidRecord When it has none, or one outside the media.
	 */
	private static function pathOf(Record $record): string
	{
		$path = $record->fields['path'] ?? null;

		if (! is_string($path) || trim($path, '/') === '' || str_contains($path, '..') || str_contains($path, '\\')) {
			throw new InvalidRecord(sprintf('Media record %s needs the "path" of its file under user/media.', $record->id));
		}

		return trim($path, '/');
	}

	/**
	 * A file's `$schema`, to keep first when it's written again.
	 *
	 * @param  ?array<array-key, mixed> $data
	 * @return array<string, mixed>
	 */
	private static function schema(?array $data): array
	{
		return is_string($data[self::SCHEMA] ?? null) ? [self::SCHEMA => $data[self::SCHEMA]] : [];
	}

	/**
	 * A record's version (D-648): a hash of what its file holds.
	 *
	 * @param array<array-key, mixed> $data
	 */
	private static function version(array $data): string
	{
		return hash('xxh128', (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
	}

	/**
	 * Writes a metadata file in the open transaction.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws RecordStoreFailure
	 */
	private function write(string $file, array $data): void
	{
		$this->transactions->remember($file);

		try {
			$this->filesystem->writeAtomic($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n", 0664);
		} catch (Throwable $error) {
			throw new RecordStoreFailure(sprintf('Unable to write %s.', $this->paths->relative($file)), previous: $error);
		}
	}

	/**
	 * Removes a metadata file in the open transaction.
	 *
	 * @throws RecordStoreFailure
	 */
	private function remove(string $file): void
	{
		if (! is_file($file)) {
			return;
		}

		$this->transactions->remember($file);

		if (! @unlink($file)) {
			throw new RecordStoreFailure(sprintf('Unable to delete %s.', $this->paths->relative($file)));
		}
	}
}
