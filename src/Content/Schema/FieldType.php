<?php

/**
 * Field types.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Schema;

use Blush\Content\Schema\Fields\BoolField;
use Blush\Content\Schema\Fields\DateField;
use Blush\Content\Schema\Fields\EnumField;
use Blush\Content\Schema\Fields\ListField;
use Blush\Content\Schema\Fields\MarkdownField;
use Blush\Content\Schema\Fields\MediaField;
use Blush\Content\Schema\Fields\NumberField;
use Blush\Content\Schema\Fields\ObjectField;
use Blush\Content\Schema\Fields\ReferenceField;
use Blush\Content\Schema\Fields\SlugField;
use Blush\Content\Schema\Fields\TextField;

/**
 * The built-in field types, keyed by the `type` used in definitions (the
 * "Type enum" of the enum + registry pattern, D-019).
 */
enum FieldType: string
{
	case Text      = 'text';
	case Markdown  = 'markdown';
	case Date      = 'date';
	case Bool      = 'bool';
	case Number    = 'number';
	case Enum      = 'enum';
	case List      = 'list';
	case Reference = 'reference';
	case Media     = 'media';
	case Slug      = 'slug';
	case Object    = 'object';

	/**
	 * Returns the type's field class.
	 *
	 * @return class-string<Field>
	 */
	public function className(): string
	{
		return match ($this) {
			self::Text      => TextField::class,
			self::Markdown  => MarkdownField::class,
			self::Date      => DateField::class,
			self::Bool      => BoolField::class,
			self::Number    => NumberField::class,
			self::Enum      => EnumField::class,
			self::List      => ListField::class,
			self::Reference => ReferenceField::class,
			self::Media     => MediaField::class,
			self::Slug      => SlugField::class,
			self::Object    => ObjectField::class
		};
	}
}
