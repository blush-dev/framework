<?php

/**
 * Content types.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Type;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Override;
use Blush\Content\EntryFields;
use Blush\Content\Schema\Field;
use Blush\Content\Schema\FieldFactory;
use Blush\Content\Schema\InvalidSchema;
use Blush\Content\Schema\Schema;

/**
 * The site's resolved content types, from every source (`ContentTypeLoader`
 * builds and checks them). Types are found by name, by path, or for a file
 * under `user/content`: a file belongs to the type whose path is the
 * nearest folder above it, and anything else is a page. So
 * `writing/forms/essay.md` is a `literary_form` even though `writing`
 * belongs to `literature`, and a bundle's `_posts/hello/index.md` is a
 * post.
 *
 * Each type's full schema is the built-in entry fields, then every
 * taxonomy's term field (which may not reuse a built-in name or alias),
 * then the type's own fields (which may replace either).
 *
 * @implements IteratorAggregate<string, ContentType>
 */
final class ContentTypes implements IteratorAggregate, Countable
{
	/**
	 * Type names keyed by path.
	 *
	 * @var array<string, string>
	 */
	private array $paths = [];

	/**
	 * Full schemas built so far, keyed by type name.
	 *
	 * @var array<string, Schema>
	 */
	private array $schemas = [];

	/**
	 * @param array<string, ContentType> $types   Keyed by name.
	 * @param array<string, TypeOrigin>  $origins Keyed by name.
	 * @param ?string                    $home    The home page's type.
	 */
	public function __construct(
		private readonly array $types,
		private readonly array $origins = [],
		public readonly ?string $home = null
	) {
		foreach ($types as $name => $type) {
			$this->paths[$type->path] = $name;
		}
	}

	/**
	 * Returns a type by name.
	 *
	 * @throws InvalidContentType When there's no such type.
	 */
	public function get(string $name): ContentType
	{
		return $this->types[$name] ?? throw new InvalidContentType(sprintf('There is no "%s" content type.', $name));
	}

	/**
	 * Returns a type by name, or `null`.
	 */
	public function find(string $name): ?ContentType
	{
		return $this->types[$name] ?? null;
	}

	/**
	 * Returns whether a type exists.
	 */
	public function has(string $name): bool
	{
		return isset($this->types[$name]);
	}

	/**
	 * Returns every type, keyed by name.
	 *
	 * @return array<string, ContentType>
	 */
	public function all(): array
	{
		return $this->types;
	}

	/**
	 * Returns where a type was defined.
	 */
	public function origin(string $name): TypeOrigin
	{
		return $this->origins[$name] ?? TypeOrigin::Config;
	}

	/**
	 * Returns the home page's type, if the home page shows a collection.
	 */
	public function homeType(): ?ContentType
	{
		return $this->home === null ? null : $this->find($this->home);
	}

	/**
	 * Returns the taxonomies, keyed by name.
	 *
	 * @return array<string, ContentType>
	 */
	public function taxonomies(): array
	{
		return array_filter($this->types, static fn (ContentType $type): bool => $type->taxonomy);
	}

	/**
	 * Returns the type whose path is exactly `$path`.
	 */
	public function byPath(string $path): ?ContentType
	{
		$name = $this->paths[trim($path, '/')] ?? null;

		return $name === null ? null : $this->types[$name];
	}

	/**
	 * Returns the type a file under `user/content` belongs to, from its
	 * path relative to that folder.
	 *
	 * @throws InvalidContentType When no type claims the content root.
	 */
	public function forFile(string $relativePath): ContentType
	{
		$directory = dirname(trim($relativePath, '/'));
		$directory = $directory === '.' ? '' : $directory;

		while (true) {
			$type = $this->byPath($directory);

			if ($type !== null) {
				return $type;
			}

			if ($directory === '') {
				throw new InvalidContentType('No content type claims the content root.');
			}

			$parent    = dirname($directory);
			$directory = $parent === '.' ? '' : $parent;
		}
	}

	/**
	 * Returns a type's full schema.
	 *
	 * @throws InvalidContentType When the fields clash.
	 */
	public function schema(string $name): Schema
	{
		if (isset($this->schemas[$name])) {
			return $this->schemas[$name];
		}

		$type = $this->get($name);

		try {
			$terms = array_values(array_filter(
				array_map(static fn (ContentType $taxonomy): ?Field => $taxonomy->termField(), $this->taxonomies()),
				static fn (?Field $field): bool => $field !== null
			));

			$schema = new Schema([...array_values(EntryFields::schema()->fields), ...$terms])->merge($type->schema);
		} catch (InvalidSchema $e) {
			throw new InvalidContentType(sprintf('Content type "%s" has clashing fields: %s', $name, $e->getMessage()), previous: $e);
		}

		return $this->schemas[$name] = $schema;
	}

	/**
	 * Returns the types as an array for a compiled cache.
	 *
	 * @return array{types: list<array<string, mixed>>, origins: array<string, string>, home: ?string}
	 */
	public function toArray(): array
	{
		return [
			'types'   => array_values(array_map(static fn (ContentType $type): array => $type->toArray(), $this->types)),
			'origins' => array_map(static fn (TypeOrigin $origin): string => $origin->value, $this->origins),
			'home'    => $this->home
		];
	}

	/**
	 * Rebuilds the types from `toArray()`'s output.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws InvalidContentType
	 */
	public static function fromArray(array $data, FieldFactory $fields): self
	{
		$types   = [];
		$origins = [];

		foreach (is_array($data['types'] ?? null) ? $data['types'] : [] as $definition) {
			if (is_array($definition)) {
				$type               = ContentType::fromArray($definition, $fields);
				$types[$type->name] = $type;
			}
		}

		foreach (is_array($data['origins'] ?? null) ? $data['origins'] : [] as $name => $origin) {
			if (is_string($name) && is_string($origin) && ($case = TypeOrigin::tryFrom($origin)) !== null) {
				$origins[$name] = $case;
			}
		}

		$home = $data['home'] ?? null;

		return new self($types, $origins, is_string($home) ? $home : null);
	}

	/**
	 * @inheritDoc
	 *
	 * @return ArrayIterator<string, ContentType>
	 */
	#[Override]
	public function getIterator(): ArrayIterator
	{
		return new ArrayIterator($this->types);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function count(): int
	{
		return count($this->types);
	}
}
