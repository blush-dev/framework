<?php

/**
 * Data relation writer.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Relation;

use Closure;
use JsonException;
use Symfony\Component\Yaml\Yaml;
use Blush\Container\Attributes\Defer;
use Blush\Content\Type\ContentConfig;
use Blush\Content\Type\ContentTypeLoader;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\InvalidContentType;
use Blush\Core\Paths;
use Blush\Data\DataException;
use Blush\Data\DataLoader;
use Blush\Support\Filesystem;

/**
 * Writes the relations the site defines in data,
 * `user/data/relations/{name}` (D-593), for the admin's Relationships
 * section and the migration from taxonomies (D-591): creates one,
 * replaces one's definition, and deletes one. A relation is written as
 * `Relation::toArray()` writes it, without its name (the file's), in
 * the file's own format (JSON for a new one).
 *
 * Every change is checked against the whole site: the file is written,
 * every type and relation is loaded again (`ContentTypeLoader`), and when
 * they don't fit together the file is put back. Writes take the content
 * types' lock, as type writes do.
 */
final readonly class DataRelationWriter
{
	/**
	 * @param Closure(): ContentTypeLoader $loader
	 */
	public function __construct(
		private Paths $paths,
		private ContentConfig $config,
		private DataLoader $data,
		private Filesystem $filesystem,
		#[Defer(ContentTypeLoader::class)] private Closure $loader
	) {}

	/**
	 * Returns a data relation's file, or `null` when it has none.
	 *
	 * @throws InvalidContentType When the name isn't a relation name.
	 */
	public function path(string $name): ?string
	{
		if (preg_match('/^[a-z][a-z0-9_]*$/', $name) !== 1) {
			throw new InvalidContentType(sprintf('"%s" isn\'t a relation name: lowercase letters, digits, and underscores, starting with a letter.', $name));
		}

		try {
			return $this->data->find($this->directory(), $name);
		} catch (DataException $error) {
			throw new InvalidContentType($error->getMessage(), previous: $error);
		}
	}

	/**
	 * Creates a relation in a new JSON file, and returns every type with
	 * it.
	 *
	 * @throws InvalidContentType When it can't be created or doesn't fit.
	 */
	public function create(Relation $relation): ContentTypes
	{
		$this->assertEnabled();

		if ($this->path($relation->name) !== null) {
			throw new InvalidContentType(sprintf('user/data/relations already defines "%s".', $relation->name));
		}

		// One from config or a plugin isn't replaced from here; it's
		// changed where it's defined.
		if (isset(($this->loader)()->load()->relations()[$relation->name])) {
			throw new InvalidContentType(sprintf('The "%s" relation is already defined in code; change it there.', $relation->name));
		}

		return $this->write("{$this->directory()}/{$relation->name}.json", $relation);
	}

	/**
	 * Replaces a data relation's definition, and returns every type with
	 * it. Only relations the site defines in data can be changed.
	 *
	 * @throws InvalidContentType When it can't be changed or doesn't fit.
	 */
	public function update(Relation $relation): ContentTypes
	{
		$this->assertEnabled();

		$path = $this->path($relation->name) ?? throw new InvalidContentType(sprintf('"%s" isn\'t defined in user/data/relations, so it can\'t be changed here.', $relation->name));

		return $this->write($path, $relation);
	}

	/**
	 * Deletes a data relation's file, and returns the types left. Links
	 * entries hold stay in their files.
	 *
	 * @throws InvalidContentType When it can't be deleted.
	 */
	public function delete(string $name): ContentTypes
	{
		$this->assertEnabled();

		$path = $this->path($name) ?? throw new InvalidContentType(sprintf('"%s" isn\'t defined in user/data/relations, so it can\'t be deleted here.', $name));

		return $this->locked(function () use ($path): ContentTypes {
			$before = (string) @file_get_contents($path);

			if (! @unlink($path)) {
				throw new InvalidContentType(sprintf('Unable to delete %s.', $this->paths->relative($path)));
			}

			return $this->checked($path, $before);
		});
	}

	/**
	 * Writes a relation to a file and checks the site still loads.
	 *
	 * @throws InvalidContentType
	 */
	private function write(string $path, Relation $relation): ContentTypes
	{
		$data = array_diff_key($relation->toArray(), ['name' => true]);

		try {
			$contents = str_ends_with($path, '.json')
				? json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n"
				: Yaml::dump($data, 4, 2);
		} catch (JsonException $error) {
			throw new InvalidContentType($error->getMessage(), previous: $error);
		}

		return $this->locked(function () use ($path, $contents): ContentTypes {
			$before = is_file($path) ? (string) file_get_contents($path) : null;

			$this->filesystem->writeAtomic($path, $contents);

			return $this->checked($path, $before);
		});
	}

	/**
	 * Loads every type and relation again, putting the file back as it was
	 * when they don't fit.
	 *
	 * @throws InvalidContentType
	 */
	private function checked(string $path, ?string $before): ContentTypes
	{
		try {
			return ($this->loader)()->load();
		} catch (InvalidContentType $error) {
			if ($before === null) {
				@unlink($path);
			} else {
				$this->filesystem->writeAtomic($path, $before);
			}

			throw $error;
		}
	}

	/**
	 * Runs a write under the content types' lock.
	 *
	 * @template T
	 * @param  Closure(): T $write
	 * @return T
	 * @throws InvalidContentType
	 */
	private function locked(Closure $write): mixed
	{
		if (! is_dir($this->paths->cache)) {
			@mkdir($this->paths->cache, 0775, true);
		}

		$lock = @fopen("{$this->paths->cache}/types.lock", 'c');

		if ($lock === false || ! flock($lock, LOCK_EX)) {
			throw new InvalidContentType('Unable to take the content types lock.');
		}

		try {
			return $write();
		} finally {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
	}

	private function directory(): string
	{
		return "{$this->paths->data}/" . RelationLoader::DATA_DIRECTORY;
	}

	/**
	 * @throws InvalidContentType
	 */
	private function assertEnabled(): void
	{
		if (! $this->config->dataTypes) {
			throw new InvalidContentType('ContentConfig "dataTypes" is off, so relations in user/data/relations aren\'t read.');
		}
	}
}
