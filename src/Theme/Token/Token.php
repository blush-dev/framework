<?php

/**
 * Design token.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Theme\Token;

/**
 * One design token (D-023): its dotted path (`color.accent`), its DTCG
 * `$type` (declared or inherited from a group), its raw `$value`, and its
 * values in other modes (`dark`, …), from the `blush.modes` extension.
 */
final readonly class Token
{
	/**
	 * @param array<string, mixed> $modes Raw values by mode name.
	 */
	public function __construct(
		public string $path,
		public mixed $value,
		public ?string $type = null,
		public array $modes = []
	) {}

	/**
	 * Returns the CSS custom property name: `color.accent` →
	 * `--color-accent`.
	 */
	public function property(): string
	{
		return '--' . str_replace('.', '-', $this->path);
	}

	/**
	 * Returns the raw value in a mode, falling back to the base value.
	 */
	public function valueIn(?string $mode): mixed
	{
		return $mode !== null && array_key_exists($mode, $this->modes) ? $this->modes[$mode] : $this->value;
	}
}
