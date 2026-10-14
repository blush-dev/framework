<?php

/**
 * Admin token.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin\Theme;

/**
 * Every token an admin theme may set (D-827), by its place in
 * `tokens.json`: groups named for what CSS calls them, keys in camelCase.
 * Its custom property in the admin's CSS is the path in kebab case, with
 * `typography.` and a `default` key left out (`color.surface.subtle` is
 * `--color-surface-subtle`, `typography.fontSize.base` is
 * `--font-size-base`). The rest of the admin's tokens (space, layout,
 * control heights, the mono font, the media overlay, and the shadows'
 * geometry) are core's alone.
 *
 * Lengths, weights, and letter spacing are kept within a range each, so a
 * theme reskins the admin but can't break its layout.
 */
enum AdminToken: string
{
	case Background              = 'color.background';
	case Surface                 = 'color.surface.default';
	case SurfaceSubtle           = 'color.surface.subtle';
	case SurfaceStrong           = 'color.surface.strong';
	case Text                    = 'color.text.default';
	case TextSecondary           = 'color.text.secondary';
	case TextMuted               = 'color.text.muted';
	case Accent                  = 'color.accent.default';
	case AccentHover             = 'color.accent.hover';
	case AccentText              = 'color.accent.text';
	case AccentSubtle            = 'color.accent.subtle';
	case AccentBorder            = 'color.accent.border';
	case Success                 = 'color.success.default';
	case SuccessSubtle           = 'color.success.subtle';
	case SuccessIndicator        = 'color.success.indicator';
	case Warning                 = 'color.warning.default';
	case WarningSubtle           = 'color.warning.subtle';
	case WarningIndicator        = 'color.warning.indicator';
	case Danger                  = 'color.danger.default';
	case DangerSubtle            = 'color.danger.subtle';
	case DangerIndicator         = 'color.danger.indicator';
	case BorderColor             = 'border.color.default';
	case BorderColorStrong       = 'border.color.strong';
	case BorderRadiusSmall       = 'border.radius.small';
	case BorderRadiusMedium      = 'border.radius.medium';
	case BorderRadiusLarge       = 'border.radius.large';
	case BoxShadowColor          = 'boxShadow.color';
	case FontFamilyUi            = 'typography.fontFamily.ui';
	case FontFamilyDisplay       = 'typography.fontFamily.display';
	case FontFamilyTitle         = 'typography.fontFamily.title';
	case FontSizeBase            = 'typography.fontSize.base';
	case FontSizeSmall           = 'typography.fontSize.small';
	case FontSizeXsmall          = 'typography.fontSize.xsmall';
	case FontSizeXxsmall         = 'typography.fontSize.xxsmall';
	case FontSizePageTitle       = 'typography.fontSize.pageTitle';
	case FontSizePanelTitle      = 'typography.fontSize.panelTitle';
	case FontSizeEntryTitle      = 'typography.fontSize.entryTitle';
	case FontSizeEditor          = 'typography.fontSize.editor';
	case FontSizeEditorTitle     = 'typography.fontSize.editorTitle';
	case FontSizeStat            = 'typography.fontSize.stat';
	case FontWeightEntryTitle    = 'typography.fontWeight.entryTitle';
	case LetterSpacingPageTitle  = 'typography.letterSpacing.pageTitle';
	case LetterSpacingEntryTitle = 'typography.letterSpacing.entryTitle';
	case PaddingRow              = 'padding.row';
	case PaddingPanel            = 'padding.panel';
	case PaddingInline           = 'padding.inline';

	/**
	 * Returns what the token holds.
	 */
	public function type(): TokenType
	{
		return match (true) {
			str_starts_with($this->value, 'color.'), $this === self::BorderColor, $this === self::BorderColorStrong, $this === self::BoxShadowColor => TokenType::Color,
			str_starts_with($this->value, 'typography.fontFamily.')    => TokenType::FontFamily,
			str_starts_with($this->value, 'typography.fontWeight.')    => TokenType::FontWeight,
			str_starts_with($this->value, 'typography.letterSpacing.') => TokenType::LetterSpacing,
			default                                                    => TokenType::Length
		};
	}

	/**
	 * Returns the custom property it sets, without the leading `--`.
	 */
	public function property(): string
	{
		$path = preg_replace(['/^typography\./', '/\.default$/'], '', $this->value) ?? $this->value;

		return strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', str_replace('.', '-', $path)) ?? $path);
	}

	/**
	 * Returns the lowest and highest values it may have: pixels for a
	 * length, ems for letter spacing, a weight for a weight; `null` for a
	 * color or a family.
	 *
	 * @return ?array{float, float}
	 */
	public function range(): ?array
	{
		return match ($this) {
			self::BorderRadiusSmall, self::BorderRadiusMedium, self::BorderRadiusLarge => [0, 24],
			self::FontSizeBase                                    => [12, 16],
			self::FontSizeSmall                                   => [11, 14],
			self::FontSizeXsmall                                  => [10, 13],
			self::FontSizeXxsmall                                 => [9, 12],
			self::FontSizePageTitle                               => [20, 40],
			self::FontSizePanelTitle                              => [13, 20],
			self::FontSizeEntryTitle                              => [12, 18],
			self::FontSizeEditor                                  => [13, 18],
			self::FontSizeEditorTitle                             => [26, 44],
			self::FontSizeStat                                    => [24, 40],
			self::FontWeightEntryTitle                            => [400, 700],
			self::LetterSpacingPageTitle, self::LetterSpacingEntryTitle => [-0.05, 0.05],
			self::PaddingRow                                      => [10, 20],
			self::PaddingPanel                                    => [12, 24],
			self::PaddingInline                                   => [16, 32],
			default                                               => null
		};
	}

	/**
	 * Returns the token at a path, a group's `default` key reached by the
	 * group's own path too (`color.surface` is `color.surface.default`), or
	 * `null` when there's none.
	 */
	public static function at(string $path): ?self
	{
		return self::tryFrom($path) ?? self::tryFrom("{$path}.default");
	}
}
