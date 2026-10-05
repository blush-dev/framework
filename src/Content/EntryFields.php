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

use Blush\Field\Fields\DateField;
use Blush\Field\Fields\EnumField;
use Blush\Field\Fields\ListField;
use Blush\Field\Fields\MarkdownField;
use Blush\Field\Fields\MediaField;
use Blush\Field\Fields\ObjectField;
use Blush\Field\Fields\SlugField;
use Blush\Field\Fields\TextField;
use Blush\Field\Schema;

/**
 * The front matter every entry understands (D-027, D-045, D-078), before a
 * type adds its own fields and each taxonomy adds its term field. The 1.x
 * names are aliases: `date` for `published`, `excerpt` for `summary`, and
 * `view` for `template`. (`author` is the author taxonomy's alias.)
 */
final class EntryFields
{
	/**
	 * The front matter key holding an entry's id (D-477): a UUID, and no
	 * field's, so no type may name a field or alias after it.
	 */
	public const string ID = 'id';

	/**
	 * Returns the built-in schema.
	 */
	public static function schema(): Schema
	{
		return new Schema([
			new TextField('title')->described('The entry\'s title.'),
			new TextField('subtitle')->described('A line shown under the title.'),
			new SlugField('slug')->described('The URL name, instead of the file name.'),
			new DateField('published')->aliases('date')->described('The publish date, such as 2026-09-26 09:00:00 -05:00. A date in the future schedules the entry.'),
			new DateField('updated')->described('When it last changed; defaults to published, then the file\'s modified time.'),
			new EnumField('status', Status::writable())->described('published (the default) or draft.'),
			new EnumField('visibility', array_column(Visibility::cases(), 'value'))->described('public (the default), unlisted, or hidden.'),
			new MarkdownField('summary')->aliases('excerpt')->described('A short Markdown summary for listings and feeds. Without one, the first 50 words are used.'),
			new MediaField('image')->described('A featured image.'),
			new TextField('locale')->described('The entry\'s locale, such as fr_CA, when it isn\'t the site\'s.'),
			new ListField('template')->aliases('view')->described('The theme template to use, such as single-wide.'),
			new TextField('layout')->described('The theme layout to use.'),
			new TextField('stylesheet')->described('An extra stylesheet for this page.'),
			new ListField('class')->described('Extra CSS classes for the page\'s <body>.'),
			new ListField('redirect_from')->described('Old URLs that should redirect here.'),
			new ObjectField('collection')->described('Lists other entries on this page.')
		]);	}
}
