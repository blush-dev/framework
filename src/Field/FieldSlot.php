<?php

/**
 * Field slot.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Field;

/**
 * A slot a kind of place offers field sets (D-347): what a set's fields
 * are to the places they're added to, such as `details` about an entry
 * or part of its `content`. Each kind declares its own
 * (`FieldTargetSource::slots()`), named for what they're for, never for
 * where a screen draws them; each admin screen maps the slots it knows
 * to its layout, so set files outlast a redesign, and a replacement admin
 * (D-222) reads the same names.
 */
final readonly class FieldSlot
{
	public function __construct(
		public string $name,
		public string $label,
		public string $description = ''
	) {}

	/**
	 * Describes the slot for the admin.
	 *
	 * @return array{name: string, label: string, description: string}
	 */
	public function toArray(): array
	{
		return ['name' => $this->name, 'label' => $this->label, 'description' => $this->description];
	}
}
