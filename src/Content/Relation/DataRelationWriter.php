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
use Blush\Container\Attributes\Defer;
use Blush\Content\ContentConfig;
use Blush\Content\Type\ContentTypeLoader;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\InvalidContentType;
use Blush\Data\DataException;
use Blush\Data\DataStore;

/**
 * Writes the relations the site defines in data,
 * `user/data/relations/{name}` (D-593), for the admin's Relationships
 * section and the migration from taxonomies (D-591): creates one,
 * replaces one's definition, and deletes one. A relation is written as
 * `Relation::toArray()` writes it, without its name (the file's), in
 * the file's own format (JSON for a new one).
 *
 * Every change is checked against the whole site: in a data store
 * transaction (D-642), the record is written, every type and relation is
 * loaded again (`ContentTypeLoader`), and when they don't fit together
 * the transaction puts the record back.
 */
final readonly class DataRelationWriter
{
	/**
	 * @param Closure(): ContentTypeLoader $loader
	 */
	public function __construct(
		private ContentConfig $config,
		private DataStore $data,
		#[Defer(ContentTypeLoader::class)] private Closure $loader
	) {}

	/**
	 * Returns where a data relation is kept
	 * (`user/data/relations/tags.json`), or `null` when the data store
	 * has none by its name.
	 *
	 * @throws InvalidContentType When the name isn't a relation name.
	 */
	public function location(string $name): ?string
	{
		if (preg_match('/^[a-z][a-z0-9_]*$/', $name) !== 1) {
			throw new InvalidContentType(sprintf('"%s" isn\'t a relation name: lowercase letters, digits, and underscores, starting with a letter.', $name));
		}

		try {
			return $this->data->has(self::record($name)) ? $this->data->location(self::record($name)) : null;
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

		if ($this->location($relation->name) !== null) {
			throw new InvalidContentType(sprintf('user/data/relations already defines "%s".', $relation->name));
		}

		// One from config or a plugin isn't replaced from here; it's
		// changed where it's defined.
		if (isset(($this->loader)()->load()->relations()[$relation->name])) {
			throw new InvalidContentType(sprintf('The "%s" relation is already defined in code; change it there.', $relation->name));
		}

		return $this->write($relation);
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

		if ($this->location($relation->name) === null) {
			throw new InvalidContentType(sprintf('"%s" isn\'t defined in user/data/relations, so it can\'t be changed here.', $relation->name));
		}

		return $this->write($relation);
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

		if ($this->location($name) === null) {
			throw new InvalidContentType(sprintf('"%s" isn\'t defined in user/data/relations, so it can\'t be deleted here.', $name));
		}

		return $this->checked(function () use ($name): void {
			$this->data->delete(self::record($name));
		});
	}

	/**
	 * Writes a relation and checks the site still loads.
	 *
	 * @throws InvalidContentType
	 */
	private function write(Relation $relation): ContentTypes
	{
		return $this->checked(function () use ($relation): void {
			$this->data->save(self::record($relation->name), array_diff_key($relation->toArray(), ['name' => true]));
		});
	}

	/**
	 * Runs a write in a transaction and loads every type and relation
	 * again, which puts the record back when they don't fit.
	 *
	 * @param  Closure(): void $write
	 * @throws InvalidContentType
	 */
	private function checked(Closure $write): ContentTypes
	{
		try {
			return $this->data->transaction(function () use ($write): ContentTypes {
				$write();

				return ($this->loader)()->load();
			});
		} catch (DataException $error) {
			throw new InvalidContentType($error->getMessage(), previous: $error);
		}
	}

	/**
	 * A relation's record name in the data store.
	 */
	private static function record(string $name): string
	{
		return RelationLoader::DATA_DIRECTORY . "/{$name}";
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
