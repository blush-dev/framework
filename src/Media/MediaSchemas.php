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
use Blush\Field\Field;
use Blush\Field\FieldSet;
use Blush\Field\FieldSets;
use Blush\Field\Fields\MarkdownField;
use Blush\Field\Fields\TextField;
use Blush\Field\InvalidSchema;
use Blush\Field\Schema;

/**
 * The metadata fields each kind of media file has (D-238, D-287, D-341),
 * as a schema: the built-in fields (`builtIn()`), then the fields of the
 * field sets attached to the kind (`media:image`, and so on), in set name
 * order (`FieldSets::schemaFor()`). A set's field can't reuse a built-in
 * field's name.
 */
final class MediaSchemas
{
	/**
	 * @var array<string, Schema>
	 */
	private array $schemas = [];

	public function __construct(private readonly FieldSets $sets)
	{}

	/**
	 * Returns a kind's built-in fields: its own first (an image's alt text
	 * leads), then `title`, `caption`, `credit`, and `description`, which
	 * every kind has.
	 */
	public static function builtIn(MediaKind $kind): Schema
	{
		$own = $kind === MediaKind::Image
			? [new TextField('alt')->labeled('Alt text')->described('What the image shows, for anyone who can\'t see it.')]
			: [];

		return new Schema([...$own, ...self::shared()]);
	}

	/**
	 * Returns every kind's built-in fields together, each once, for the
	 * editor schema (`media.schema.json`), since a metadata file doesn't
	 * say its kind.
	 */
	public static function allBuiltIn(): Schema
	{
		$fields = [];

		foreach (MediaKind::cases() as $kind) {
			foreach (self::builtIn($kind)->fields as $name => $field) {
				$fields[$name] ??= $field;
			}
		}

		return new Schema($fields);
	}

	/**
	 * Returns a kind's schema.
	 *
	 * @throws InvalidConfig When a set's fields don't fit the kind.
	 */
	public function schema(MediaKind $kind): Schema
	{
		if (isset($this->schemas[$kind->value])) {
			return $this->schemas[$kind->value];
		}

		try {
			return $this->schemas[$kind->value] = $this->sets->schemaFor(new MediaKindTarget($kind));
		} catch (InvalidSchema $e) {
			throw new InvalidConfig($e->getMessage(), previous: $e);
		}
	}

	/**
	 * Returns the field sets attached to a kind, in name order.
	 *
	 * @return list<FieldSet>
	 */
	public function setsFor(MediaKind $kind): array
	{
		return $this->sets->for(MediaKindTarget::keyFor($kind));
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
	 * The fields every kind has.
	 *
	 * @return list<Field>
	 */
	private static function shared(): array
	{
		return [
			new TextField('title')->described('What the library calls it, in place of its file name.'),
			new TextField('caption')->described('Shown with the file where it\'s used, such as under an image.'),
			new TextField('credit')->described('Who made it, or where it\'s from.'),
			new MarkdownField('description')->described('A longer description, for the library.')
		];
	}
}
