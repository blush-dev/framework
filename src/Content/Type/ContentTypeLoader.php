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
use Blush\Content\Schema\FieldFactory;
use Blush\Core\Paths;
use Blush\Data\DataLoader;
use Blush\Data\InvalidData;

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
 *    They may redefine a built-in type, but redefining an extension or
 *    config type is an error.
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
		private Container $container
	) {}

	/**
	 * Loads and checks the types.
	 *
	 * @throws InvalidContentType
	 */
	public function load(): ContentTypes
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

		foreach ($this->config->types as $type) {
			$types[$type->name]   = $type;
			$origins[$type->name] = TypeOrigin::Config;
		}

		foreach ($this->dataTypes() as $type) {
			$origin = $origins[$type->name] ?? null;

			if ($origin === TypeOrigin::Extension || $origin === TypeOrigin::Config) {
				throw new InvalidContentType(sprintf(
					'The "%s" content type is defined in both %s and user/data/%s; define it in one place.',
					$type->name,
					$origin === TypeOrigin::Config ? 'config/content.php' : 'an extension',
					self::DATA_DIRECTORY
				));
			}

			$types[$type->name]   = $type;
			$origins[$type->name] = TypeOrigin::Data;
		}

		$resolved = new ContentTypes($types, $origins, $this->config->home);
		$this->check($resolved);

		return $resolved;
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
	 * Returns the data-defined types, when the config allows them.
	 *
	 * @return list<ContentType>
	 * @throws InvalidContentType
	 */
	private function dataTypes(): array
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

			$types[] = ContentType::fromArray(['name' => $name, ...$definition], $this->fields);
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
		$authors = array_keys(array_filter($types->all(), static fn (ContentType $type): bool => $type instanceof Authors));

		if (count($authors) > 1) {
			throw new InvalidContentType(sprintf('A site has one authors type, but "%s" are all authors types.', implode('", "', $authors)));
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
