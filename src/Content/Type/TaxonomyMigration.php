<?php

/**
 * Taxonomy migration.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Type;

use Closure;
use JsonException;
use Throwable;
use Blush\Container\Attributes\Defer;
use Blush\Content\Relation\RelationLoader;
use Blush\Content\Writer\DataFileKeys;
use Blush\Core\Paths;
use Blush\Data\DataLoader;
use Blush\Support\Filesystem;

/**
 * Migrates the data types still written as taxonomies (D-591, D-593),
 * for `content:taxonomies` and Site Health (D-478's tool for D-078's
 * exception): each `user/data/types/{name}` file becomes a collection,
 * edited in place so its other keys and comments stay, and its classify
 * relation is written to `user/data/relations/{name}.json`
 * (`LegacyTaxonomy`). The site is loaded again after each, and both files
 * are put back when it doesn't load. A taxonomy defined in PHP can't be
 * rewritten; it fails to load, saying what to change.
 */
final readonly class TaxonomyMigration
{
	/**
	 * @param Closure(): ContentTypeLoader $loader
	 */
	public function __construct(
		private Paths $paths,
		private DataLoader $data,
		private Filesystem $filesystem,
		#[Defer(ContentTypeLoader::class)] private Closure $loader
	) {}

	/**
	 * Returns the data types still written as taxonomies.
	 *
	 * @return list<string>
	 * @throws InvalidContentType
	 */
	public function report(): array
	{
		return ($this->loader)()->load()->legacy;
	}

	/**
	 * Migrates them, and returns what was written: each type's files by
	 * name, and why any failed.
	 *
	 * @return array{migrated: array<string, list<string>>, failed: array<string, string>}
	 * @throws InvalidContentType When the types can't be read at all.
	 */
	public function migrate(): array
	{
		$migrated = [];
		$failed   = [];

		foreach ($this->report() as $name) {
			try {
				$migrated[$name] = $this->locked(fn (): array => $this->migrateOne($name));
			} catch (Throwable $error) {
				$failed[$name] = $error->getMessage();
			}
		}

		return ['migrated' => $migrated, 'failed' => $failed];
	}

	/**
	 * Migrates one type, returning its files (relative to the site).
	 *
	 * @return list<string>
	 * @throws InvalidContentType
	 */
	private function migrateOne(string $name): array
	{
		$types     = "{$this->paths->data}/" . ContentTypeLoader::DATA_DIRECTORY;
		$relations = "{$this->paths->data}/" . RelationLoader::DATA_DIRECTORY;
		$typePath  = $this->data->find($types, $name) ?? throw new InvalidContentType(sprintf('user/data/types has no "%s".', $name));
		$before    = (string) file_get_contents($typePath);
		$found     = $this->data->find($relations, $name);

		if ($found !== null) {
			throw new InvalidContentType(sprintf('%s already exists; move it aside first.', $this->paths->relative($found)));
		}

		$definition         = $this->data->load($types, $name) ?? [];
		[$type, $relation]  = LegacyTaxonomy::convert($name, $definition);
		$relationPath       = "{$relations}/{$name}.json";
		$sets               = [];

		foreach (array_keys($definition) as $key) {
			if (! array_key_exists($key, $type)) {
				$sets[(string) $key] = null;
			}
		}

		foreach ($type as $key => $value) {
			if (! array_key_exists($key, $definition) || $definition[$key] !== $value) {
				$sets[(string) $key] = $value;
			}
		}

		try {
			$json = json_encode($relation, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
		} catch (JsonException $error) {
			throw new InvalidContentType($error->getMessage(), previous: $error);
		}

		$this->filesystem->writeAtomic($relationPath, $json);
		$this->filesystem->writeAtomic($typePath, DataFileKeys::edit($typePath, $before, $sets));

		try {
			($this->loader)()->load();
		} catch (InvalidContentType $error) {
			@unlink($relationPath);
			$this->filesystem->writeAtomic($typePath, $before);

			throw $error;
		}

		return [$this->paths->relative($typePath), $this->paths->relative($relationPath)];
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
}
