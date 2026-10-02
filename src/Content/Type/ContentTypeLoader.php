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
use Blush\Core\Paths;
use Blush\Data\DataLoader;
use Blush\Data\InvalidData;
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
 * 3. The site's `ContentConfig` types.
 * 4. Data types from `user/data/types/{name}.json|yaml`, when allowed.
 *    They may redefine a built-in type. A file named for an extension or
 *    config collection or taxonomy changes it instead (D-349): each
 *    option it sets replaces the code's (`ContentType::overriddenBy()`),
 *    and the type keeps its origin. One named for a code pages or profiles
 *    type is an error.
 *
 * A people field reading a key a taxonomy's term field reads (1.x's
 * `author` taxonomy, with `authors` and `author`) is dropped, so the
 * taxonomy keeps it (D-351).
 *
 * Then: folders must be unique, one type must claim the content root, the
 * types named by listings, taxonomies' `types`, feed categories, and the
 * home page must exist, and every type's fields must fit together.
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
		private FieldSetLoader $sets
	) {}

	/**
	 * Loads and checks the types.
	 *
	 * @throws InvalidContentType
	 */
	public function load(): ContentTypes
	{
		[$types, $origins] = $this->codeTypes();

		$overrides = [];

		foreach ($this->dataDefinitions() as $name => $definition) {
			$origin = $origins[$name] ?? null;

			if ($origin === TypeOrigin::Extension || $origin === TypeOrigin::Config) {
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

		$claimed = [];

		foreach ($types as $type) {
			if ($type instanceof Taxonomy) {
				array_push($claimed, $type->field, ...$type->aliases);
			}
		}

		$types    = array_map(static fn (ContentType $type): ContentType => $type->withoutPeopleReading(...$claimed), $types);
		$resolved = new ContentTypes($types, $origins, $this->config->home, $sets, $overrides);
		$this->check($resolved);

		return $resolved;
	}

	/**
	 * Returns the types code defines, before any data: the built-in
	 * types, extension types, then the config's, each with its origin.
	 * The admin overrides code types against these (D-349).
	 *
	 * @return array{array<string, ContentType>, array<string, TypeOrigin>}
	 * @throws InvalidContentType
	 */
	public function codeTypes(): array
	{
		$types   = [];
		$origins = [];

		foreach (BuiltInType::cases() as $builtIn) {
			if (! in_array($builtIn->value, $this->config->disabled, true)) {
				$types[$builtIn->value]   = $builtIn->type();
				$origins[$builtIn->value] = TypeOrigin::BuiltIn;
			}
		}

		foreach ($this->extensionTypes() as $type) {
			if (($origins[$type->name] ?? null) === TypeOrigin::Extension) {
				throw new InvalidContentType(sprintf('Two extensions define the "%s" content type.', $type->name));
			}

			$types[$type->name]   = $type;
			$origins[$type->name] = TypeOrigin::Extension;
		}

		foreach ($this->configTypes() as $type) {
			$types[$type->name]   = $type;
			$origins[$type->name] = TypeOrigin::Config;
		}

		return [$types, $origins];
	}

	/**
	 * Returns the config's types, building those in array form with every
	 * registered field type.
	 *
	 * @return list<ContentType>
	 * @throws InvalidContentType
	 */
	private function configTypes(): array
	{
		$types = $this->config->types;

		foreach ($this->config->definitions as $definition) {
			try {
				$types[] = ContentType::fromArray($definition, $this->fields);
			} catch (InvalidContentType $e) {
				throw new InvalidContentType(sprintf('config/content.php: %s', $e->getMessage()), previous: $e);
			}
		}

		return $types;
	}

	/**
	 * Returns the types from extension sources.
	 *
	 * @return iterable<ContentType>
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

			yield from $source->types();
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

		foreach ($types as $name => $type) {
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

			if ($type instanceof Taxonomy) {
				$references[] = ['termListing type', $type->termListing->type];

				foreach ($type->types as $reference) {
					$references[] = ['types', $reference];
				}
			}

			foreach ($references as [$option, $reference]) {
				if ($reference !== null && ! $types->has($reference)) {
					throw new InvalidContentType(sprintf('Content type "%s" %s names "%s", which doesn\'t exist.', $name, $option, $reference));
				}
			}

			if ($type->feed !== false && $type->feed->categories !== null && ! $types->get($type->feed->categories) instanceof Taxonomy) {
				throw new InvalidContentType(sprintf('Content type "%s" feed categories "%s" isn\'t a taxonomy.', $name, $type->feed->categories));
			}

			$types->schema($name);
		}

		if (! isset($folders[''])) {
			throw new InvalidContentType('No content type claims the content root; the "page" type normally does.');
		}

		if ($types->home !== null && ! $types->has($types->home)) {
			throw new InvalidContentType(sprintf('ContentConfig "home" names "%s", which isn\'t a content type.', $types->home));
		}
	}
}
