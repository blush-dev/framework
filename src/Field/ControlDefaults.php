<?php

/**
 * Control defaults.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Field;

use Blush\Field\Controls\Audio;
use Blush\Field\Controls\Checkboxes;
use Blush\Field\Controls\Code;
use Blush\Field\Controls\Date;
use Blush\Field\Controls\Email;
use Blush\Field\Controls\File;
use Blush\Field\Controls\Group;
use Blush\Field\Controls\Icon;
use Blush\Field\Controls\Image;
use Blush\Field\Controls\Link;
use Blush\Field\Controls\Markdown;
use Blush\Field\Controls\Number;
use Blush\Field\Controls\Reference;
use Blush\Field\Controls\Repeater;
use Blush\Field\Controls\Select;
use Blush\Field\Controls\Text;
use Blush\Field\Controls\Toggle;
use Blush\Field\Controls\Video;
use Blush\Field\Controls\ViewOnly;
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
use Blush\Media\MediaKind;

/**
 * The control a field is drawn with when its form chooses none (D-795):
 * the control layer's, never the field's. Core's field types have theirs
 * here; another field type (a plugin's) has the one registered for its
 * type key (`register()`), else it's shown read-only.
 */
final class ControlDefaults
{
	/**
	 * The default controls of other field types, by type key.
	 *
	 * @var array<string, class-string<Control>>
	 */
	private array $types = [];

	/**
	 * Names the control a field type is drawn with by default.
	 *
	 * @param class-string<Control> $control
	 */
	public function register(string $type, string $control): void
	{
		$this->types[$type] = $control;
	}

	/**
	 * Returns the control a field is drawn with when its form chooses
	 * none.
	 */
	public function for(Field $field): Control
	{
		$class = $this->types[$field->type()] ?? null;

		return self::core($field) ?? ($class === null ? new ViewOnly() : new $class());
	}

	/**
	 * Returns a core field type's default control, or `null` for another
	 * type.
	 */
	public static function core(Field $field): ?Control
	{
		return match (true) {
			$field instanceof BoolField      => new Toggle(),
			$field instanceof DateField      => new Date(),
			$field instanceof EnumField      => new Select(),
			$field instanceof MarkdownField  => new Markdown(),
			$field instanceof NumberField    => new Number(),
			$field instanceof ObjectField    => new Group(),
			$field instanceof SlugField      => new Code(),
			$field instanceof ReferenceField => $field->to === '' ? new Code() : new Reference(),
			$field instanceof MediaField     => match ($field->kind) {
				MediaKind::Image => new Image(),
				MediaKind::Video => new Video(),
				MediaKind::Audio => new Audio(),
				default          => new File()
			},
			$field instanceof TextField      => match ($field->format) {
				TextFormat::Url   => new Link(),
				TextFormat::Email => new Email(),
				TextFormat::Icon  => new Icon(),
				null              => new Text()
			},
			$field instanceof ListField      => match (true) {
				$field->item instanceof EnumField => new Checkboxes(),
				new Repeater()->fits($field)      => new Repeater(),
				default                           => new ViewOnly()
			},
			default                          => null
		};
	}
}
