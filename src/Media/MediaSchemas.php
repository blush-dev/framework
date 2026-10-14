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
use Blush\Field\FieldControls;
use Blush\Field\FieldSet;
use Blush\Field\FieldSets;
use Blush\Field\Fields\MarkdownField;
use Blush\Field\Fields\TextField;
use Blush\Field\InvalidSchema;
use Blush\Field\Schema;
use Blush\Translation\Messages;

/**
 * The metadata fields each kind of media file has (D-238, D-287, D-341),
 * as a schema: the built-in fields (`builtIn()`), then the fields of the
 * field sets attached to the kind (`media:image`, and so on), in set name
 * order (`FieldSets::schemaFor()`). A set's field can't reuse a built-in
 * field's name, or claim `id`, `owner`, `uploaded`, `renditions`, or
 * `artwork`, which aren't fields (D-407, D-487, D-488, D-581, D-709).
 */
final class MediaSchemas
{
	/**
	 * @var array<string, Schema>
	 */
	private array $schemas = [];

	public function __construct(
		private readonly FieldSets $sets,
		private readonly Messages $messages
	) {}

	/**
	 * Returns a kind's built-in fields: `title` first, so every kind's form
	 * opens the same way, then its own (an image's alt text), then
	 * `caption`, `credit`, and the description (`content`, a record's
	 * content, D-674), which every kind has.
	 */
	public static function builtIn(MediaKind $kind, Messages $messages): Schema
	{
		$own = $kind === MediaKind::Image
			? [new TextField('alt')->labeled($messages->t('media.media_schemas.alt', 'Alt text'))->described($messages->t('media.media_schemas.alt_description', 'What the image shows, for anyone who can\'t see it.'))]
			: [];

		return new Schema([
			new TextField('title')->described($messages->t('media.media_schemas.title_description', 'What the library calls it, in place of its file name.')),
			...$own,
			...self::shared($messages)
		]);
	}

	/**
	 * Returns every kind's built-in fields together, each once, for the
	 * editor schema (`media.schema.json`), since a metadata file doesn't
	 * say its kind.
	 */
	public static function allBuiltIn(Messages $messages): Schema
	{
		$fields = [];

		foreach (MediaKind::cases() as $kind) {
			foreach (self::builtIn($kind, $messages)->fields as $name => $field) {
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
			$schema = $this->sets->schemaFor(new MediaKindTarget($kind, $this->messages));
		} catch (InvalidSchema $e) {
			throw new InvalidConfig($e->getMessage(), previous: $e);
		}

		foreach ([MediaMetadata::ID, MediaMetadata::OWNER, MediaMetadata::UPLOADED, MediaMetadata::RENDITIONS, MediaMetadata::ARTWORK] as $key) {
			if ($schema->field($key) !== null) {
				throw new InvalidConfig((string) $this->messages->t(
					'media.media_schemas.reserved',
					'A field set for {kind} media has a field (or alias) named "{field}", which is reserved for the file\'s {field, select, owner {uploader} uploaded {upload date} other {{field}}}; rename it.',
					kind: $kind->value,
					field: $key
				));
			}
		}

		return $this->schemas[$kind->value] = $schema;
	}

	/**
	 * Returns a kind's schema as its file's screen draws it: its full
	 * schema without the fields extensions register (D-791).
	 *
	 * @throws InvalidConfig
	 */
	public function formSchema(MediaKind $kind): Schema
	{
		return $this->sets->drawn($this->schema($kind), MediaKindTarget::keyFor($kind));
	}

	/**
	 * Returns the controls a kind's fields are drawn with (D-795): each of
	 * its field sets'.
	 */
	public function controls(MediaKind $kind): FieldControls
	{
		$controls = [];

		foreach ($this->setsFor($kind) as $set) {
			$controls = [...$controls, ...$set->controls->controls];
		}

		return new FieldControls($controls);
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
	 * The fields every kind has after its own.
	 *
	 * @return list<Field>
	 */
	private static function shared(Messages $messages): array
	{
		return [
			new TextField('caption')->described($messages->t('media.media_schemas.caption_description', 'Shown with the file where it\'s used, such as under an image.')),
			new TextField('credit')->described($messages->t('media.media_schemas.credit_description', 'Who made it, or where it\'s from.')),
			new MarkdownField(MediaMetadata::CONTENT)->labeled($messages->t('media.media_schemas.description', 'Description'))->described($messages->t('media.media_schemas.description_description', 'A longer description, for the library.'))
		];
	}
}
