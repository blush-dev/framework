<?php

/**
 * Content type loader.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Type;

use Blush\Container\Container;
use Blush\Content\ContentConfig;
use Blush\Content\Relation\InvalidRelation;
use Blush\Content\Relation\Relation;
use Blush\Content\Relation\RelationCompiler;
use Blush\Content\Relation\RelationKind;
use Blush\Content\Relation\RelationLoader;
use Blush\Content\Relation\RelationOrigin;
use Blush\Core\Paths;
use Blush\Data\DataLoader;
use Blush\Data\InvalidData;
use Blush\Extension\DefinitionClash;
use Blush\Field\FieldFactory;
use Blush\Field\FieldSetLoader;
use Blush\Field\InvalidSchema;

/**
 * Gathers the content types from every source and checks that they fit
 * together (D-042, D-043). In order, later sources replacing earlier ones
 * by name:
 *
 * 1. The built-in types (`BuiltInType`), minus those the config disables.
 * 2. Extension types, from sources tagged `ContentTypeSource::TAG`. Two
 *    extensions defining one name is an error.
 * 3. Data types from `user/data/types/{name}.json`, when allowed.
 *    They may redefine a built-in type. A file named for an extension
 *    collection changes it instead (D-349): each
 *    option it sets replaces the code's (`ContentType::overriddenBy()`),
 *    and the type keeps its origin. One named for a code pages or profiles
 *    type is an error.
 *
 * The relation definitions load with them (`RelationLoader`, D-593). A
 * data type still in the taxonomy form is read as the collection and
 * classify relation that replaced it (`LegacyTaxonomy`) and listed in
 * `ContentTypes::$legacy` until `content:taxonomies --write` (or Site
 * Health) writes them (D-591); a taxonomy anywhere else is an error.
 *
 * A credit relation (D-602) must point at the profiles type, and a
 * type's `byline` must name one of its credit relations.
 *
 * Then: folders must be unique, one type must claim the content root, the
 * types named by listings, relations, feed categories, and the homepage
 * must exist, feed categories must be classified by, and every type's
 * fields must fit together.
 */
final readonly class ContentTypeLoader
{
	/**
	 * The folder under `user/data` holding data-defined types.
	 */
	public const string DATA_DIRECTORY = 'types';

	public function __construct(
		private ContentConfig $config,
		private Paths $paths,
		private DataLoader $data,
		private FieldFactory $fields,
		private Container $container,
		private FieldSetLoader $sets,
		private RelationLoader $relations
	) {}

	/**
	 * Loads and checks the types.
	 *
	 * @throws InvalidContentType
	 */
	public function load(): ContentTypes
	{
		[$types, $origins, $clashes] = $this->codeTypes();

		$overrides = [];
		$legacy    = [];
		$converted = [];

		foreach ($this->dataDefinitions() as $name => $definition) {
			$origin = $origins[$name] ?? null;

			// Only a type of the site's own data is read as what replaced
			// it; a file over a code type can't make it a taxonomy.
			if (LegacyTaxonomy::is($definition) && $origin !== TypeOrigin::Extension) {
				[$definition, $relation] = LegacyTaxonomy::convert($name, $definition);
				$legacy[]                = $name;
				$converted[$name]        = $relation;
			}

			if ($origin === TypeOrigin::Extension) {
				$types[$name] = $types[$name]->overriddenBy($definition, $this->fields);
				$overrides[]  = $name;
				continue;
			}

			$types[$name]   = ContentType::fromArray(['name' => $name, ...$definition], $this->fields);
			$origins[$name] = TypeOrigin::Data;
		}

		try {
			$sets = $this->sets->load();
		} catch (InvalidSchema $e) {
			throw new InvalidContentType($e->getMessage(), previous: $e);
		}

		try {
			[$relations, $relationOrigins, $relationClashes] = $this->relations->load();

			foreach ($converted as $name => $definition) {
				if (! isset($relations[$name])) {
					$relations[$name]       = Relation::fromArray(['name' => $name, ...$definition]);
					$relationOrigins[$name] = RelationOrigin::Data;
				}
			}
		} catch (InvalidRelation $e) {
			throw new InvalidContentType($e->getMessage(), previous: $e);
		}

		$resolved = new ContentTypes($types, $origins, $this->config->home, $sets, $overrides, $relations, $relationOrigins, $legacy, [...$clashes, ...$relationClashes]);
		$this->check($resolved);

		return $resolved;
	}

	/**
	 * Returns the types code defines, before any data: the built-in
	 * types, then extension types, each with its origin.
	 * The admin overrides code types against these (D-349).
	 *
	 * Two extensions defining a type by one name keep the first, and the
	 * clash is returned (D-597).
	 *
	 * @return array{array<string, ContentType>, array<string, TypeOrigin>, list<DefinitionClash>}
	 * @throws InvalidContentType
	 */
	public function codeTypes(): array
	{
		$types   = [];
		$origins = [];
		$sources = [];
		$clashes = [];

		foreach (BuiltInType::cases() as $builtIn) {
			if (! in_array($builtIn->value, $this->config->disabled, true)) {
				$types[$builtIn->value]   = $builtIn->type();
				$origins[$builtIn->value] = TypeOrigin::BuiltIn;
			}
		}

		foreach ($this->extensionTypes() as [$type, $source]) {
			if (isset($sources[$type->name])) {
				$clashes[] = new DefinitionClash('type', $type->name, $sources[$type->name], $source);

				continue;
			}

			$types[$type->name]   = $type;
			$origins[$type->name] = TypeOrigin::Extension;
			$sources[$type->name] = $source;
		}

		return [$types, $origins, $clashes];
	}

	/**
	 * Returns the types from extension sources, each with its source's
	 * class.
	 *
	 * @return iterable<array{ContentType, string}>
	 */
	private function extensionTypes(): iterable
	{
		foreach ($this->container->tagged(ContentTypeSource::TAG) as $source) {
			if (! $source instanceof ContentTypeSource) {
				throw new InvalidContentType(sprintf(
					'Services tagged "%s" must implement %s; %s does not.',
					ContentTypeSource::TAG,
					ContentTypeSource::class,
					get_debug_type($source)
				));
			}

			foreach ($source->types() as $type) {
				yield [$type, $source::class];
			}
		}
	}

	/**
	 * Returns the definitions in `user/data/types`, keyed by name, when
	 * the config allows them: whole types, or changes to a type from code.
	 *
	 * @return array<string, array<array-key, mixed>>
	 * @throws InvalidContentType
	 */
	private function dataDefinitions(): array
	{
		if (! $this->config->dataTypes) {
			return [];
		}

		$directory = $this->paths->data . '/' . self::DATA_DIRECTORY;

		try {
			$definitions = $this->data->loadAll($directory);
		} catch (InvalidData $e) {
			throw new InvalidContentType($e->getMessage(), previous: $e);
		}

		$types = [];

		foreach ($definitions as $name => $definition) {
			$declared = $definition['name'] ?? $name;

			if ($declared !== $name) {
				throw new InvalidContentType(sprintf(
					'user/data/%s/%s names the type "%s"; a data type is named after its file.',
					self::DATA_DIRECTORY,
					$name,
					is_scalar($declared) ? (string) $declared : get_debug_type($declared)
				));
			}

			$urls = array_find(['urls', 'routing'], static fn (string $key): bool => array_key_exists($key, $definition));

			if (! $this->config->dataTypeUrls && $urls !== null) {
				throw new InvalidContentType(sprintf(
					'The "%s" data type sets "%s", which ContentConfig "dataTypeUrls" doesn\'t allow.',
					$name,
					$urls
				));
			}

			$types[$name] = $definition;
		}

		return $types;
	}

	/**
	 * Checks that the types fit together.
	 *
	 * @throws InvalidContentType
	 */
	private function check(ContentTypes $types): void
	{
		$folders = [];
		$profiles = array_keys(array_filter($types->all(), static fn (ContentType $type): bool => $type instanceof Profiles));

		if (count($profiles) > 1) {
			throw new InvalidContentType(sprintf('A site has one profiles type, but "%s" are all profiles types.', implode('", "', $profiles)));
		}

		foreach ($types->relations() as $relation) {
			if ($relation->kind === RelationKind::Credit && $relation->to !== $profiles) {
				throw new InvalidContentType($profiles === []
					? sprintf('Relation "%s" credits people, but the site has no profiles type.', $relation->name)
					: sprintf('Relation "%s" credits people, so it points at the profiles type: "to" must be ["%s"].', $relation->name, $profiles[0]));
			}
		}

		foreach ($types as $name => $type) {
			if ($type->byline !== null && ! isset($types->credits($name)[$type->byline])) {
				throw new InvalidContentType(sprintf('Content type "%s" names "%s" as its byline, which isn\'t a credit relation from it.', $name, $type->byline));
			}

			if (isset($folders[$type->folder])) {
				throw new InvalidContentType(sprintf(
					'The "%s" and "%s" content types share the folder "%s".',
					$folders[$type->folder],
					$name,
					$type->folder
				));
			}

			$folders[$type->folder] = $name;

			$references = [
				['listing type', $type->listing->type],
				['feed categories', $type->feed === false ? null : $type->feed->categories],
				['feed listing type', $type->feed === false ? null : $type->feed->listing?->type]
			];

			foreach ($references as [$option, $reference]) {
				if ($reference !== null && ! $types->has($reference)) {
					throw new InvalidContentType(sprintf('Content type "%s" %s names "%s", which doesn\'t exist.', $name, $option, $reference));
				}
			}

			if ($type->feed !== false && $type->feed->categories !== null && $types->classification($type->feed->categories) === null) {
				throw new InvalidContentType(sprintf('Content type "%s" feed categories "%s" isn\'t a type a classify relation files entries under.', $name, $type->feed->categories));
			}

			$types->schema($name);
		}

		if (! isset($folders[''])) {
			throw new InvalidContentType('No content type claims the content root; the "page" type normally does.');
		}

		try {
			new RelationCompiler()->compile($types);
		} catch (InvalidRelation $e) {
			throw new InvalidContentType($e->getMessage(), previous: $e);
		}

		foreach ($types->relations() as $relation) {
			if ($relation->inverse !== false && $relation->inverse->listing->type !== null && ! $types->has($relation->inverse->listing->type)) {
				throw new InvalidContentType(sprintf('Relation "%s" inverse listing type names "%s", which doesn\'t exist.', $relation->name, $relation->inverse->listing->type));
			}
		}

		if ($types->home !== null && ! $types->has($types->home)) {
			throw new InvalidContentType(sprintf('ContentConfig "home" names "%s", which isn\'t a content type.', $types->home));
		}
	}
}
