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
use Blush\Extension\DefinitionClash;
use Blush\Field\FieldFactory;
use Blush\Field\FieldSetLoader;
use Blush\Field\InvalidSchema;
use Blush\Storage\Record\RecordException;
use Blush\Support\RequestMemo;
use Blush\Translation\Messages;

/**
 * Gathers the content types from every source and checks that they fit
 * together (D-042, D-043). In order, later sources replacing earlier ones
 * by name:
 *
 * 1. The built-in types (`BuiltInType`), minus those the config disables.
 * 2. Extension types, from sources tagged `ContentTypeSource::TAG`. Two
 *    extensions defining one name is an error.
 * 3. Data types from the `types` table (`DefinitionTables`, D-672; on
 *    files `user/data/types/{name}.json`), when allowed.
 *    They may redefine a built-in type. A file named for an extension
 *    collection changes it instead (D-349): each
 *    option it sets replaces the code's (`ContentType::overriddenBy()`),
 *    and the type keeps its origin. One named for a code pages or profiles
 *    type is an error. One that sets only `position` and `under` places
 *    a type from code in the admin's menu, built-in types too, and
 *    changes nothing else (D-726).
 *
 * The relation definitions load with them (`RelationLoader`, D-593). A
 * data type still in the taxonomy form is read as the collection and
 * classify relation that replaced it (`LegacyTaxonomy`) and listed in
 * `ContentTypes::$legacy` until `content:taxonomies --write` (or Site
 * Health) writes them (D-591); a taxonomy anywhere else is an error.
 * Likewise a data type that still names its folder is read in `_` and
 * its name (`LegacyFolder`) and listed in `ContentTypes::$namedFolders`
 * until `content:type-folders --write` (or Site Health) moves it
 * (D-683); a type from code naming one is an error.
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
	public function __construct(
		private ContentConfig $config,
		private DefinitionTables $tables,
		private FieldFactory $fields,
		private Container $container,
		private FieldSetLoader $sets,
		private RelationLoader $relations,
		private Messages $messages,
		private RequestMemo $memo = new RequestMemo()
	) {}

	/**
	 * Loads and checks the types, once a request (D-807); a write to the
	 * tables they're read from drops what was loaded.
	 *
	 * @throws InvalidContentType
	 */
	public function load(): ContentTypes
	{
		return $this->memo->remember('content-types|loaded', $this->loadTypes(...));
	}

	/**
	 * Loads and checks the types, for `load()`.
	 *
	 * @throws InvalidContentType
	 */
	private function loadTypes(): ContentTypes
	{
		[$types, $origins, $clashes] = $this->codeTypes();

		$overrides = [];
		$legacy    = [];
		$converted = [];
		$named     = [];

		foreach ($this->dataDefinitions() as $name => $definition) {
			$origin = $origins[$name] ?? null;

			// Only a type of the site's own data is read as what replaced
			// it; a file over a code type can't make it a taxonomy.
			if (LegacyTaxonomy::is($definition) && $origin !== TypeOrigin::Extension) {
				[$definition, $relation] = LegacyTaxonomy::convert($name, $definition);
				$legacy[]                = $name;
				$converted[$name]        = $relation;
			}

			if (LegacyFolder::is($definition)) {
				$kind       = $origin === TypeOrigin::Extension ? $types[$name]->kind() : TypeKind::tryFrom(is_string($definition['kind'] ?? null) ? $definition['kind'] : '') ?? TypeKind::Collection;
				$definition = LegacyFolder::convert($name, $definition, $kind, $origin !== TypeOrigin::Extension && $this->config->dataTypeUrls);
				$named[]    = $name;
			}

			// A file that only places a type in the admin's menu lays over
			// any type from code, built-in ones too, without changing it
			// otherwise (D-726).
			if ($origin !== null && ContentType::onlyPlaces($definition)) {
				$types[$name] = $types[$name]->placedBy($definition, $this->fields, $this->messages);
				continue;
			}

			if ($origin === TypeOrigin::Extension) {
				$types[$name] = $types[$name]->overriddenBy($definition, $this->fields, $this->messages);
				$overrides[]  = $name;
				continue;
			}

			$types[$name]   = ContentType::fromArray(['name' => $name, ...$definition], $this->fields, $this->messages);
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
					$relations[$name]       = Relation::fromArray(['name' => $name, ...$definition], $this->messages);
					$relationOrigins[$name] = RelationOrigin::Data;
				}
			}
		} catch (InvalidRelation $e) {
			throw new InvalidContentType($e->getMessage(), previous: $e);
		}

		$resolved = new ContentTypes($types, $origins, $this->config->home, $sets, $overrides, $relations, $relationOrigins, $legacy, [...$clashes, ...$relationClashes], $named, $this->messages);
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

		try {
			$definitions = $this->tables->types()->all();
		} catch (RecordException $e) {
			throw new InvalidContentType($e->getMessage(), previous: $e);
		}

		$types = [];

		foreach ($definitions as $name => $definition) {
			$urls = array_find(['urls', 'routing', 'prefix'], static fn (string $key): bool => array_key_exists($key, $definition));

			if (! $this->config->dataTypeUrls && $urls !== null) {
				throw new InvalidContentType((string) $this->messages->t(
					'content.content_type_loader.data_type_urls',
					'The "{name}" data type sets "{option}", which ContentConfig "dataTypeUrls" doesn\'t allow.',
					name: $name,
					option: $urls
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
			throw new InvalidContentType((string) $this->messages->t('content.content_type_loader.profiles_types', 'A site has one profiles type, but {types} are all profiles types.', types: '"' . implode('", "', $profiles) . '"'));
		}

		foreach ($types->relations() as $relation) {
			if ($relation->kind === RelationKind::Credit && $relation->to !== $profiles) {
				throw new InvalidContentType((string) ($profiles === []
					? $this->messages->t('content.content_type_loader.credit_no_profiles', 'Relation "{name}" credits people, but the site has no profiles type.', name: $relation->name)
					: $this->messages->t('content.content_type_loader.credit_to', 'Relation "{name}" credits people, so it points at the profiles type: "to" must be ["{profiles}"].', name: $relation->name, profiles: $profiles[0])));
			}
		}

		foreach ($types as $name => $type) {
			if ($type->byline !== null && ! isset($types->credits($name)[$type->byline])) {
				throw new InvalidContentType((string) $this->messages->t('content.content_type_loader.bad_byline', 'Content type "{name}" names "{byline}" as its byline, which isn\'t a credit relation from it.', name: $name, byline: $type->byline));
			}

			if (isset($folders[$type->folder])) {
				throw new InvalidContentType((string) $this->messages->t(
					'content.content_type_loader.shared_folder',
					'The "{first}" and "{second}" content types share the folder "{folder}".',
					first: $folders[$type->folder],
					second: $name,
					folder: $type->folder
				));
			}

			$folders[$type->folder] = $name;

			$references = [
				['listing', $type->listing->type],
				['categories', $type->feed === false ? null : $type->feed->categories],
				['feed_listing', $type->feed === false ? null : $type->feed->listing?->type]
			];

			foreach ($references as [$option, $reference]) {
				if ($reference !== null && ! $types->has($reference)) {
					throw new InvalidContentType((string) $this->messages->t(
						'content.content_type_loader.missing_reference',
						'Content type "{name}" {option, select, listing {listing type} categories {feed categories} other {feed listing type}} names "{reference}", which doesn\'t exist.',
						name: $name,
						option: $option,
						reference: $reference
					));
				}
			}

			if ($type->feed !== false && $type->feed->categories !== null && $types->classification($type->feed->categories) === null) {
				throw new InvalidContentType((string) $this->messages->t(
					'content.content_type_loader.bad_categories',
					'Content type "{name}" feed categories "{categories}" isn\'t a type a classify relation files entries under.',
					name: $name,
					categories: $type->feed->categories
				));
			}

			$types->schema($name);
		}

		if (! isset($folders[''])) {
			throw new InvalidContentType('No content type claims the content root; the "page" type normally does.');
		}

		try {
			new RelationCompiler($this->messages)->compile($types);
		} catch (InvalidRelation $e) {
			throw new InvalidContentType($e->getMessage(), previous: $e);
		}

		foreach ($types->relations() as $relation) {
			if ($relation->inverse !== false && $relation->inverse->listing->type !== null && ! $types->has($relation->inverse->listing->type)) {
				throw new InvalidContentType((string) $this->messages->t(
					'content.content_type_loader.missing_inverse_type',
					'Relation "{name}" inverse listing type names "{type}", which doesn\'t exist.',
					name: $relation->name,
					type: $relation->inverse->listing->type
				));
			}
		}

		if ($types->home !== null && ! $types->has($types->home)) {
			throw new InvalidContentType(sprintf('ContentConfig "home" names "%s", which isn\'t a content type.', $types->home));
		}
	}
}
