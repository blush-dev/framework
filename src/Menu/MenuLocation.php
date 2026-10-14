<?php

/**
 * Menu location.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Menu;

use Stringable;
use Blush\Translation\Messages;

/**
 * A place a theme shows a menu, declared in `theme.json` `menus` (D-199,
 * D-200): a label string, or an object with a `label`, the deepest
 * nesting it shows (`depth`), extra per-item `fields` (field
 * definitions, as theme settings are), and the `items` it shows until
 * the site assigns it a menu (D-676), as a menu's items are written.
 * Its label is translated from the theme's catalog
 * (`manifest.menus.{name}`, D-824) once `Menus` has read it, so it's
 * text, printed in the current language.
 *
 * ```json
 * "menus": {
 *     "primary": { "label": "Primary navigation", "depth": 2, "fields": { "columns": { "type": "number", "integer": true } } },
 *     "social": { "label": "Social links", "items": [{ "url": "https://example.org/", "label": "Example" }] }
 * }
 * ```
 */
final readonly class MenuLocation
{
	/**
	 * @param ?int                                  $depth  The deepest level shown (1 is the top), or `null` for any.
	 * @param array<string, array<array-key, mixed>> $fields Field definitions, by name.
	 * @param list<mixed>                           $items  The theme's default items, raw.
	 */
	public function __construct(
		public string $name,
		public Stringable|string $label = '',
		public ?int $depth = null,
		public array $fields = [],
		public array $items = []
	) {}

	/**
	 * Builds a location from its declaration, saying what's wrong with it
	 * with `$messages`.
	 *
	 * @throws MenuException When it has the wrong shape.
	 */
	public static function fromDeclaration(string $name, mixed $value, string $theme, Messages $messages): self
	{
		if (is_string($value)) {
			return new self($name, trim($value));
		}

		if (! is_array($value) || ($value !== [] && array_is_list($value))) {
			throw new MenuException((string) $messages->t('menu.menu_location.shape', 'The "{theme}" theme\'s menu location "{location}" must be a label or an object.', theme: $theme, location: $name));
		}

		$label  = $value['label'] ?? '';
		$depth  = $value['depth'] ?? null;
		$fields = $value['fields'] ?? [];
		$items  = $value['items'] ?? [];

		if (! is_string($label)) {
			throw new MenuException((string) $messages->t('menu.menu_location.label', 'The "{theme}" theme\'s menu location "{location}" has a "label" that isn\'t a string.', theme: $theme, location: $name));
		}

		if ($depth !== null && (! is_int($depth) || $depth < 1)) {
			throw new MenuException((string) $messages->t('menu.menu_location.depth', 'The "{theme}" theme\'s menu location "{location}" has a "depth" that isn\'t a whole number from 1.', theme: $theme, location: $name));
		}

		if (! is_array($fields) || ($fields !== [] && array_is_list($fields)) || ! array_all($fields, static fn (mixed $field): bool => is_array($field))) {
			throw new MenuException((string) $messages->t('menu.menu_location.fields', 'The "{theme}" theme\'s menu location "{location}" must map "fields" names to field definitions.', theme: $theme, location: $name));
		}

		if (! is_array($items) || ! array_is_list($items)) {
			throw new MenuException((string) $messages->t('menu.menu_location.items', 'The "{theme}" theme\'s menu location "{location}" has "items" that aren\'t a list.', theme: $theme, location: $name));
		}

		/** @var array<string, array<array-key, mixed>> $fields */
		return new self($name, trim($label), $depth, $fields, $items);
	}

	/**
	 * Returns a copy with another label.
	 */
	public function withLabel(Stringable|string $label): self
	{
		return new self($this->name, $label, $this->depth, $this->fields, $this->items);
	}
}
