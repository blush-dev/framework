<?php

/**
 * Theme preview.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Theme;

use Blush\Translation\Messages;

/**
 * What the admin draws a theme's preview from (D-381), as `theme.json`'s
 * `preview` declares it: a layout, a line about its type (such as "Serif
 * headings · sans body"), and its palette, six colors each with a light
 * and a dark value. The admin sketches the page from these instead of
 * showing a screenshot, so the preview can't go stale and no webfont is
 * fetched for it.
 *
 *     "preview": {
 *         "layout": "centered",
 *         "type": "Serif headings · sans body",
 *         "palette": {
 *             "background": ["#ffffff", "#0f1115"],
 *             "accent": "#1f4ed8"
 *         }
 *     }
 *
 * A color is a hex value, or a `[light, dark]` pair; one value is used
 * for both. Every role is required once there's a palette. Colors are
 * kept as six-digit lowercase hex.
 */
final readonly class ThemePreview
{
	/**
	 * The palette's roles, in order.
	 */
	public const array ROLES = ['background', 'surface', 'text', 'muted', 'accent', 'border'];

	/**
	 * Matches a hex color: `#rgb` or `#rrggbb`.
	 */
	public const string COLOR = '^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$';

	/**
	 * @param array<string, array{string, string}> $palette Light and dark colors, by role, or none.
	 */
	public function __construct(
		public PreviewLayout $layout = PreviewLayout::Centered,
		public string $type = '',
		public array $palette = []
	) {}

	/**
	 * Builds a preview from a manifest's `preview` object.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws ThemeException When a value is missing or doesn't fit.
	 */
	public static function fromArray(string $theme, array $data, Messages $messages = new Messages()): self
	{
		$unknown = array_diff(array_map(strval(...), array_keys($data)), ['layout', 'type', 'palette']);

		if ($unknown !== []) {
			throw new ThemeException((string) $messages->t('theme.theme_preview.unknown_key', 'The "{theme}" theme\'s "preview" has "layout", "type", and "palette"; "{field}" isn\'t one.', theme: $theme, field: reset($unknown)));
		}

		$layout = $data['layout'] ?? PreviewLayout::Centered->value;
		$layout = is_string($layout) ? PreviewLayout::tryFrom($layout) : null;

		if ($layout === null) {
			throw new ThemeException((string) $messages->t('theme.theme_preview.layout', 'The "{theme}" theme\'s "preview.layout" must be {layouts}.', theme: $theme, layouts: implode(', ', array_column(PreviewLayout::cases(), 'value'))));
		}

		$type = $data['type'] ?? '';

		if (! is_string($type)) {
			throw new ThemeException((string) $messages->t('theme.theme_preview.type', 'The "{theme}" theme\'s "preview.type" must be a string.', theme: $theme));
		}

		return new self($layout, trim($type), self::palette($theme, $data['palette'] ?? null, $messages));
	}

	/**
	 * Returns what the admin's API gives: the layout, type, and palette
	 * (`null` without one).
	 *
	 * @return array{layout: string, type: string, palette: ?array<string, array{string, string}>}
	 */
	public function toArray(): array
	{
		return ['layout' => $this->layout->value, 'type' => $this->type, 'palette' => $this->palette === [] ? null : $this->palette];
	}

	/**
	 * Reads the palette: every role, each a color or a `[light, dark]`
	 * pair.
	 *
	 * @return array<string, array{string, string}>
	 * @throws ThemeException
	 */
	private static function palette(string $theme, mixed $palette, Messages $messages): array
	{
		if ($palette === null) {
			return [];
		}

		$error = static fn (): ThemeException => new ThemeException((string) $messages->t(
			'theme.theme_preview.palette',
			'The "{theme}" theme\'s "preview.palette" must give {roles}, each a hex color (such as "#1f4ed8") or a [light, dark] pair.',
			theme: $theme,
			roles: implode(', ', self::ROLES)
		));

		if (! is_array($palette) || array_is_list($palette) || array_diff(array_map(strval(...), array_keys($palette)), self::ROLES) !== []) {
			throw $error();
		}

		$colors = [];

		foreach (self::ROLES as $role) {
			$value = $palette[$role] ?? null;
			$pair  = is_string($value) ? [$value, $value] : $value;

			if (! is_array($pair) || ! array_is_list($pair) || count($pair) !== 2 || ! is_string($pair[0]) || ! is_string($pair[1])) {
				throw $error();
			}

			$colors[$role] = [self::color($pair[0]) ?? throw $error(), self::color($pair[1]) ?? throw $error()];
		}

		return $colors;
	}

	/**
	 * Returns a hex color as six lowercase digits, or `null` when it
	 * isn't one.
	 */
	private static function color(string $value): ?string
	{
		if (preg_match('/' . self::COLOR . '/', $value) !== 1) {
			return null;
		}

		$hex = strtolower(substr($value, 1));

		return '#' . (strlen($hex) === 3 ? $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2] : $hex);
	}
}
