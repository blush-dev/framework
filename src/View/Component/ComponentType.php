<?php

/**
 * Built-in component types.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View\Component;

use Blush\Content\Schema\Field;
use Blush\Content\Schema\Fields\EnumField;
use Blush\Content\Schema\Fields\MediaField;
use Blush\Content\Schema\Fields\NumberField;
use Blush\Content\Schema\Fields\TextField;

/**
 * The core content components (the "Type enum" of D-019): the ones
 * content can use in any theme (D-033), since the default theme, at the
 * end of every chain, has their templates. They're in the `blush`
 * namespace, and the only components with short names (D-171). Each is
 * seeded into the `ComponentRegistry`, most as template-only.
 */
enum ComponentType: string
{
	case Callout = 'callout';
	case Embed   = 'embed';
	case Figure  = 'figure';
	case Gallery = 'gallery';

	/**
	 * Returns the component's class, or `null` for a template-only one.
	 *
	 * @return ?class-string<Component>
	 */
	public function className(): ?string
	{
		return match ($this) {
			self::Embed => Embed::class,
			default     => null
		};
	}

	/**
	 * Returns the component's full name.
	 */
	public function componentName(): ComponentName
	{
		return new ComponentName(ComponentName::CORE, $this->value);
	}

	/**
	 * Returns what a template-only component wraps, or `null` to use its
	 * class's.
	 */
	public function content(): ?ComponentContent
	{
		return match ($this) {
			self::Callout, self::Gallery => ComponentContent::Blocks,
			self::Figure                 => ComponentContent::Text,
			self::Embed                  => null
		};
	}

	/**
	 * Returns a template-only component's props, or `null` to read them
	 * from its class.
	 *
	 * @return ?list<Field>
	 */
	public function props(): ?array
	{
		return match ($this) {
			self::Callout => [new EnumField('tone', ['note', 'info', 'tip', 'warning', 'danger'])->default('note')],
			self::Figure  => [new MediaField('src')->required(), new TextField('alt')],
			self::Gallery => [new NumberField('columns', integer: true, min: 1, max: 6)->default(3)],
			self::Embed   => null
		};
	}
}
