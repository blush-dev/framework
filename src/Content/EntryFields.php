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

use Override;
use Blush\Field\Controls\Reference;
use Blush\Field\Controls\Text;
use Blush\Field\FieldControls;
use Blush\Field\Fields\DateField;
use Blush\Field\Fields\EnumField;
use Blush\Field\Fields\MarkdownField;
use Blush\Field\Fields\MediaField;
use Blush\Field\Fields\ObjectField;
use Blush\Field\Fields\ReferenceField;
use Blush\Field\Fields\SlugField;
use Blush\Field\Fields\TextField;
use Blush\Field\TargetKey;
use Blush\Field\FieldSource;
use Blush\Field\RegisteredField;
use Blush\Field\Schema;
use Blush\Media\MediaKind;
use Blush\Translation\Messages;

/**
 * The front matter every entry understands (D-027, D-045, D-078), before a
 * type adds its own fields and each taxonomy adds its term field. The 1.x
 * names are aliases: `excerpt` for `summary`, and `view` for `template`.
 * The entry's date is `date` (D-852), whatever its status. (`author` is the author taxonomy's alias.) Core
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

	public function __construct(
		private readonly Messages $messages
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function fields(): iterable
	{
		foreach (self::schema($this->messages)->fields as $field) {
			yield new RegisteredField(RegisteredField::CORE, $field, ['type:' . TargetKey::EVERY]);
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
	public static function schema(Messages $messages): Schema
	{
		return new Schema([
			new TextField('title')->labeled($messages->t('content.entry_fields.title.label', 'Title'))->described($messages->t('content.entry_fields.title.description', 'The entry\'s title.')),
			new TextField('subtitle')->labeled($messages->t('content.entry_fields.subtitle.label', 'Subtitle'))->described($messages->t('content.entry_fields.subtitle.description', 'A line shown under the title.')),
			new SlugField('slug')->labeled($messages->t('content.entry_fields.slug.label', 'Slug'))->described($messages->t('content.entry_fields.slug.description', 'The URL name, instead of the file name.')),
			new DateField('date')->labeled($messages->t('content.entry_fields.date.label', 'Date'))->described($messages->t('content.entry_fields.date.description', 'The entry\'s date, such as 2026-09-26 09:00:00 -05:00. A date in the future schedules the entry.')),
			new DateField('expires')->labeled($messages->t('content.entry_fields.expires.label', 'Expires'))->described($messages->t('content.entry_fields.expires.description', 'When the entry comes off the site, in a type with expiry, such as 2026-12-31 23:59:00 -05:00. Moving or clearing it brings the entry back.')),
			new DateField('updated')->labeled($messages->t('content.entry_fields.updated.label', 'Updated'))->described($messages->t('content.entry_fields.updated.description', 'When it last changed; defaults to the date, then the file\'s modified time.')),
			new TextField('status')->labeled($messages->t('content.entry_fields.status.label', 'Status'))->described($messages->t('content.entry_fields.status.description', 'published (the default), draft, trash, or another status the site registers.')),
			new DateField(self::TRASHED)->labeled($messages->t('content.entry_fields.trashed.label', 'Trashed'))->described($messages->t('content.entry_fields.trashed.description', 'When the entry was moved to the trash, set with status: trash.')),
			new EnumField('visibility', array_column(Visibility::cases(), 'value'))->labeled($messages->t('content.entry_fields.visibility.label', 'Visibility'))->described($messages->t('content.entry_fields.visibility.description', 'public (the default), unlisted, or hidden.')),
			new MarkdownField('summary')->labeled($messages->t('content.entry_fields.summary.label', 'Summary'))->aliases('excerpt')->described($messages->t('content.entry_fields.summary.description', 'A short Markdown summary for listings and feeds. Without one, the first 50 words are used.')),
			new MediaField('image', MediaKind::Image)->labeled($messages->t('content.entry_fields.image.label', 'Featured image'))->described($messages->t('content.entry_fields.image.description', 'A featured image.')),
			new TextField('template')->labeled($messages->t('content.entry_fields.template.label', 'Template'))->aliases('view')->described($messages->t('content.entry_fields.template.description', 'The theme template to use, such as single-wide.')),
			new TextField('stylesheet')->labeled($messages->t('content.entry_fields.stylesheet.label', 'Stylesheet'))->described($messages->t('content.entry_fields.stylesheet.description', 'An extra stylesheet for this page.')),
			new TextField('class')->labeled($messages->t('content.entry_fields.class.label', 'Classes'))->described($messages->t('content.entry_fields.class.description', 'Extra CSS classes for the page\'s <body>, separated by spaces.')),
			new ReferenceField(self::TRANSLATION_OF, multiple: false)->labeled($messages->t('content.entry_fields.translation_of.label', 'Original'))->described($messages->t('content.entry_fields.translation_of.description', 'For a translation, the id of the entry it translates, which links them whatever their file names.')),
			new ObjectField('collection')->labeled($messages->t('content.entry_fields.collection.label', 'Collection'))->described($messages->t('content.entry_fields.collection.description', 'Lists other entries on this page.'))
		]);
	}
}
