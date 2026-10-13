<?php

/**
 * Admin theme contrast.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\AdminTheme;

use Blush\Auth\ColorScheme;

/**
 * Checks an admin theme's colors against WCAG's contrast ratios (D-827),
 * in both color schemes: text on the background and surfaces (4.5:1, or
 * 3:1 for muted text, which only ever sits beside text that's readable),
 * accent text on the accent, a status color on its own soft fill, and the
 * accent against the surface it marks (3:1, as a control's edge needs).
 * A low ratio is warned of, never refused: a theme may know better, and
 * the admin still works.
 */
final readonly class AdminThemeContrast
{
	/**
	 * Each pair checked: the foreground, the background, and the ratio it
	 * needs.
	 *
	 * @var list<array{AdminToken, AdminToken, float}>
	 */
	private const array PAIRS = [
		[AdminToken::Text, AdminToken::Background, 4.5],
		[AdminToken::Text, AdminToken::Surface, 4.5],
		[AdminToken::TextSecondary, AdminToken::Surface, 4.5],
		[AdminToken::TextMuted, AdminToken::Surface, 3.0],
		[AdminToken::AccentText, AdminToken::Accent, 4.5],
		[AdminToken::Accent, AdminToken::Surface, 3.0],
		[AdminToken::Success, AdminToken::SuccessSubtle, 4.5],
		[AdminToken::Warning, AdminToken::WarningSubtle, 4.5],
		[AdminToken::Danger, AdminToken::DangerSubtle, 4.5]
	];

	/**
	 * Returns a warning for each pair below its ratio, in either scheme,
	 * given everything a theme shows (Neutral's tokens with its own laid
	 * over them). A color with any transparency isn't judged, since what
	 * shows through it varies.
	 *
	 * @return list<string>
	 */
	public static function check(AdminTokens $tokens): array
	{
		$warnings = [];

		foreach (self::PAIRS as [$foreground, $background, $needed]) {
			$front = $tokens->color($foreground);
			$back  = $tokens->color($background);

			if ($front === null || $back === null) {
				continue;
			}

			foreach ([ColorScheme::Light, ColorScheme::Dark] as $scheme) {
				$a = $front->in($scheme);
				$b = $back->in($scheme);

				if ($a->alpha < 1.0 || $b->alpha < 1.0) {
					continue;
				}

				$ratio = $a->contrast($b);

				if ($ratio < $needed) {
					$warnings[] = sprintf(
						'In the %s scheme, %s on %s is %.2f:1, under the %s:1 it needs.',
						$scheme->value,
						$foreground->value,
						$background->value,
						$ratio,
						rtrim(rtrim(number_format($needed, 1), '0'), '.')
					);
				}
			}
		}

		return $warnings;
	}
}
