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
use Throwable;
use Blush\Container\Attributes\Defer;
use Blush\Content\Relation\RelationLoader;
use Blush\Data\DataKeys;
use Blush\Data\DataStore;

/**
 * Migrates the data types still written as taxonomies (D-591, D-593),
 * for `content:taxonomies` and Site Health (D-478's tool for D-078's
 * exception): each `user/data/types/{name}` file becomes a collection,
 * edited in place so its other keys stay, and its classify
 * relation is written to `user/data/relations/{name}.json`
 * (`LegacyTaxonomy`). The site is loaded again after each, in a data
 * store transaction (D-642), which puts both records back when it
 * doesn't load. A taxonomy defined in PHP can't be
 * rewritten; it fails to load, saying what to change.
 */
final readonly class TaxonomyMigration
{
	/**
	 * @param Closure(): ContentTypeLoader $loader
	 */
	public function __construct(
		private DataStore $data,
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
	 * Migrates them, and returns what was written: where each type's
	 * records are kept, by name, and why any failed.
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
				$migrated[$name] = $this->data->transaction(fn (): array => $this->migrateOne($name));
			} catch (Throwable $error) {
				$failed[$name] = $error->getMessage();
			}
		}

		return ['migrated' => $migrated, 'failed' => $failed];
	}

	/**
	 * Migrates one type, returning where its records are kept.
	 *
	 * @return list<string>
	 * @throws InvalidContentType
	 */
	private function migrateOne(string $name): array
	{
		$typeRecord     = ContentTypeLoader::DATA_DIRECTORY . "/{$name}";
		$relationRecord = RelationLoader::DATA_DIRECTORY . "/{$name}";
		$definition     = $this->data->load($typeRecord) ?? throw new InvalidContentType(sprintf('user/data/types has no "%s".', $name));

		if ($this->data->has($relationRecord)) {
			throw new InvalidContentType(sprintf('%s already exists; move it aside first.', $this->data->location($relationRecord)));
		}

		[$type, $relation] = LegacyTaxonomy::convert($name, $definition);
		$sets              = [];

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

		$this->data->save($relationRecord, $relation);
		$this->data->save($typeRecord, DataKeys::apply($definition, $sets));

		($this->loader)()->load();

		return [$this->data->location($typeRecord), $this->data->location($relationRecord)];
	}
}
