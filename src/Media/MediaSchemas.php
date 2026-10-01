<?php

/**
 * Media schemas.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media;

use Blush\Config\InvalidConfig;
use Blush\Container\Container;
use Blush\Content\Schema\FieldFactory;
use Blush\Content\Schema\Fields\MarkdownField;
use Blush\Content\Schema\Fields\TextField;
use Blush\Content\Schema\InvalidSchema;
use Blush\Content\Schema\Schema;
use Blush\Core\Paths;
use Blush\Data\DataException;
use Blush\Data\DataLoader;

/**
 * The metadata fields each kind of media file has (D-238, D-287), as a
 * schema, from, in order, each one replacing a field of the same name
 * before it:
 *
 * 1. The built-in fields: `title`, `caption`, `credit`, and
 *    `description` for every kind, and `alt` for images.
 * 2. Extensions' sets (`MediaFieldSource`).
 * 3. The site's data file, `user/data/media-fields.{json,yaml,yml}`: a
 *    map of `all`, `image`, `video`, `audio`, and `file` to lists of
 *    field definitions, as a data content type's `fields` are.
 * 4. The site's `config/media.php` (`MediaConfig::$fields`).
 *
 * A kind's schema is its own fields first (an image's alt text leads),
 * then the fields every kind has; a kind's field replaces one of every
 * kind's with the same name.
 */
final class MediaSchemas
{
	/**
	 * The data file under `user/data` that defines a site's fields.
	 */
	public const string DATA_FILE = 'media-fields';

	/**
	 * The keys of the data file: every kind, then each kind.
	 */
	private const string ALL = 'all';

	/**
	 * @var array<string, Schema>
	 */
	private array $schemas = [];

	/**
	 * @var ?list<MediaFieldSet>
	 */
	private ?array $sets = null;

	public function __construct(
		private readonly MediaConfig $config,
		private readonly FieldFactory $fields,
		private readonly DataLoader $data,
		private readonly Paths $paths,
		private readonly Container $container
	) {}

	/**
	 * The built-in field sets.
	 *
	 * @return list<MediaFieldSet>
	 */
	public static function builtIn(): array
	{
		return [
			new MediaFieldSet([
				new TextField('title')->described('What the library calls it, in place of its file name.'),
				new TextField('caption')->described('Shown with the file where it\'s used, such as under an image.'),
				new TextField('credit')->described('Who made it, or where it\'s from.'),
				new MarkdownField('description')->described('A longer description, for the library.')
			]),
			new MediaFieldSet([
				new TextField('alt')->labeled('Alt text')->described('What the image shows, for anyone who can\'t see it.')
			], MediaKind::Image)
		];
	}

	/**
	 * Returns a kind's schema.
	 *
	 * @throws InvalidConfig When the fields can't be read or clash.
	 */
	public function schema(MediaKind $kind): Schema
	{
		if (isset($this->schemas[$kind->value])) {
			return $this->schemas[$kind->value];
		}

		$shared = [];
		$own    = [];

		foreach ($this->sets() as $set) {
			if (! $set->appliesTo($kind)) {
				continue;
			}

			foreach ($set->fields as $field) {
				if ($set->kind === null) {
					$shared[$field->name] = $field;
				} else {
					$own[$field->name] = $field;
				}
			}
		}

		try {
			return $this->schemas[$kind->value] = new Schema([...$own, ...array_diff_key($shared, $own)]);
		} catch (InvalidSchema $e) {
			throw new InvalidConfig(sprintf('The %s media fields clash: %s', $kind->value, $e->getMessage()), previous: $e);
		}
	}

	/**
	 * Returns the schema for a file's kind.
	 *
	 * @throws InvalidConfig
	 */
	public function forFile(MediaFile $file): Schema
	{
		return $this->schema(MediaKind::fromMime($file->mime));
	}

	/**
	 * Every field set, in the order they apply.
	 *
	 * @return list<MediaFieldSet>
	 * @throws InvalidConfig
	 */
	private function sets(): array
	{
		return $this->sets ??= [...self::builtIn(), ...$this->extensionSets(), ...$this->dataSets(), ...$this->config->fields];
	}

	/**
	 * @return list<MediaFieldSet>
	 * @throws InvalidConfig
	 */
	private function extensionSets(): array
	{
		$sets = [];

		foreach ($this->container->tagged(MediaFieldSource::TAG) as $source) {
			if (! $source instanceof MediaFieldSource) {
				throw new InvalidConfig(sprintf('Services tagged "%s" must implement %s; %s does not.', MediaFieldSource::TAG, MediaFieldSource::class, get_debug_type($source)));
			}

			foreach ($source->fieldSets() as $set) {
				$sets[] = $set;
			}
		}

		return $sets;
	}

	/**
	 * The site's data file's sets.
	 *
	 * @return list<MediaFieldSet>
	 * @throws InvalidConfig
	 */
	private function dataSets(): array
	{
		try {
			$data = $this->data->load($this->paths->data, self::DATA_FILE);
		} catch (DataException $e) {
			throw new InvalidConfig(sprintf('user/data/%s can\'t be read: %s', self::DATA_FILE, $e->getMessage()), previous: $e);
		}

		return $data === null ? [] : self::setsFromArray($data, $this->fields, sprintf('user/data/%s', self::DATA_FILE));
	}

	/**
	 * Reads field sets from a map of `all` and each kind to lists of field
	 * definitions, as the data file and `MediaConfig::fromArray()` have
	 * them.
	 *
	 * @param  array<array-key, mixed> $data
	 * @return list<MediaFieldSet>
	 * @throws InvalidConfig
	 */
	public static function setsFromArray(array $data, FieldFactory $fields, string $where): array
	{
		$kinds = [self::ALL, ...array_column(MediaKind::cases(), 'value')];
		$sets  = [];

		foreach ($data as $key => $list) {
			if (! in_array($key, $kinds, true)) {
				throw new InvalidConfig(sprintf('%s has "%s"; media fields are grouped under %s.', $where, $key, implode(', ', $kinds)));
			}

			if (! is_array($list) || ! array_is_list($list)) {
				throw new InvalidConfig(sprintf('%s "%s" must be a list of field definitions.', $where, $key));
			}

			$definitions = [];

			foreach ($list as $definition) {
				if (! is_array($definition)) {
					throw new InvalidConfig(sprintf('%s "%s" must hold field definitions.', $where, $key));
				}

				try {
					$definitions[] = $fields->fromArray($definition);
				} catch (InvalidSchema $e) {
					throw new InvalidConfig(sprintf('%s "%s": %s', $where, $key, $e->getMessage()), previous: $e);
				}
			}

			$sets[] = new MediaFieldSet($definitions, $key === self::ALL ? null : MediaKind::from($key));
		}

		return $sets;
	}
}
