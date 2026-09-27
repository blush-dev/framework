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

use Blush\Content\Schema\Fields\DateField;
use Blush\Content\Schema\Fields\EnumField;
use Blush\Content\Schema\Fields\ListField;
use Blush\Content\Schema\Fields\MarkdownField;
use Blush\Content\Schema\Fields\MediaField;
use Blush\Content\Schema\Fields\ObjectField;
use Blush\Content\Schema\Fields\SlugField;
use Blush\Content\Schema\Fields\TextField;
use Blush\Content\Schema\Schema;

/**
 * The front matter every entry understands (D-027, D-045, D-078), before a
 * type adds its own fields and each taxonomy adds its term field. The 1.x
 * names are aliases: `date` for `published`, `excerpt` for `summary`, and
 * `view` for `template`. (`author` is the author taxonomy's alias.)
 */
final class EntryFields
{
	/**
	 * Returns the built-in schema.
	 */
	public static function schema(): Schema
	{
		return new Schema([
			new TextField('title'),
			new TextField('subtitle'),
			new SlugField('slug'),
			new DateField('published')->aliases('date'),
			new DateField('updated'),
			new EnumField('status', Status::writable()),
			new EnumField('visibility', array_column(Visibility::cases(), 'value')),
			new MarkdownField('summary')->aliases('excerpt'),
			new MediaField('image'),
			new TextField('locale'),
			new ListField('template')->aliases('view'),
			new TextField('layout'),
			new TextField('stylesheet'),
			new ListField('class'),
			new ListField('redirect_from'),
			new ObjectField('collection')
		]);
	}
}
