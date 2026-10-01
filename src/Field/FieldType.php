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

namespace Blush\Field;

use Blush\Field\Fields\BoolField;
use Blush\Field\Fields\DateField;
use Blush\Field\Fields\EnumField;
use Blush\Field\Fields\ListField;
use Blush\Field\Fields\MarkdownField;
use Blush\Field\Fields\MediaField;
use Blush\Field\Fields\NumberField;
use Blush\Field\Fields\ObjectField;
use Blush\Field\Fields\ReferenceField;
use Blush\Field\Fields\SlugField;
use Blush\Field\Fields\TextField;

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
