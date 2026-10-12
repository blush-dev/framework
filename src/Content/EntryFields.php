<?php

/**
 * Built-in entry fields.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content;

use Blush\Field\Controls\Reference;
use Blush\Field\Controls\Text;
use Blush\Field\FieldControls;
use Blush\Field\FieldSet;
use Blush\Field\FieldSource;
use Blush\Field\Fields\DateField;
use Blush\Field\Fields\EnumField;
use Blush\Field\Fields\MarkdownField;
use Blush\Field\Fields\MediaField;
use Blush\Field\Fields\ObjectField;
use Blush\Field\Fields\ReferenceField;
use Blush\Field\Fields\SlugField;
use Blush\Field\Fields\TextField;
use Blush\Field\RegisteredField;
use Blush\Field\Schema;
use Blush\Media\MediaKind;
use Override;

/**
 * The front matter every entry understands (D-027, D-045, D-078), before a
 * type adds its own fields and each taxonomy adds its term field. The 1.x
 * names are aliases: `date` for `published`, `excerpt` for `summary`, and
 * `view` for `template`. (`author` is the author taxonomy's alias.) Core
 * registers them on every type (D-790), and each type's own fields start
 * with them.
 */
final class EntryFields implements FieldSource
{
	/**
	 * The front matter key holding an entry's id (D-477): a UUID, and no
	 * field's, so no type may name a field or alias after it.
	 */
	public const string ID = 'id';

	/**
	 * When an entry was moved to the trash (D-484), set with `status:
	 * trash` and removed when it's restored.
	 */
	public const string TRASHED = 'trashed';

	/**
	 * The id of the entry a translation translates (D-511), which links
	 * them whatever their file names; without it, a translation links by
	 * its name beside its original (D-455, D-460).
	 */
	public const string TRANSLATION_OF = 'translation_of';

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function fields(): iterable
	{
		foreach (self::schema()->fields as $field) {
			yield new RegisteredField(RegisteredField::CORE, $field, ['type:' . FieldSet::EVERY]);
		}
	}

	/**
	 * Returns the controls the editor draws core's fields with where they
	 * aren't their types' defaults (D-795): a slug as one line, and a
	 * translation's original picked from its entries.
	 */
	public static function controls(): FieldControls
	{
		return new FieldControls(['slug' => new Text(), self::TRANSLATION_OF => new Reference()]);
	}

	/**
	 * Returns the built-in schema.
	 */
	public static function schema(): Schema
	{
		return new Schema([
			new TextField('title')->labeled('Title')->described('The entry\'s title.'),
			new TextField('subtitle')->labeled('Subtitle')->described('A line shown under the title.'),
			new SlugField('slug')->labeled('Slug')->described('The URL name, instead of the file name.'),
			new DateField('published')->labeled('Published')->aliases('date')->described('The publish date, such as 2026-09-26 09:00:00 -05:00. A date in the future schedules the entry.'),
			new DateField('updated')->labeled('Updated')->described('When it last changed; defaults to published, then the file\'s modified time.'),
			new EnumField('status', Status::writable())->labeled('Status')->described('published (the default), draft, or trash.'),
			new DateField(self::TRASHED)->labeled('Trashed')->described('When the entry was moved to the trash, set with status: trash.'),
			new EnumField('visibility', array_column(Visibility::cases(), 'value'))->labeled('Visibility')->described('public (the default), unlisted, or hidden.'),
			new MarkdownField('summary')->labeled('Summary')->aliases('excerpt')->described('A short Markdown summary for listings and feeds. Without one, the first 50 words are used.'),
			new MediaField('image', MediaKind::Image)->labeled('Featured image')->described('A featured image.'),
			new TextField('template')->labeled('Template')->aliases('view')->described('The theme template to use, such as single-wide.'),
			new TextField('stylesheet')->labeled('Stylesheet')->described('An extra stylesheet for this page.'),
			new TextField('class')->labeled('Classes')->described('Extra CSS classes for the page\'s <body>, separated by spaces.'),
			new ReferenceField(self::TRANSLATION_OF, multiple: false)->labeled('Original')->described('For a translation, the id of the entry it translates, which links them whatever their file names.'),
			new ObjectField('collection')->labeled('Collection')->described('Lists other entries on this page.')
		]);
	}
}
