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

namespace Blush\Admin\Theme;

use Blush\Auth\ColorScheme;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * Checks an admin theme's colors against WCAG's contrast ratios (D-827),
 * in both color schemes: text on the background and surfaces (4.5:1, or
 * 3:1 for muted text, which only ever sits beside text that's readable),
 * accent text on the accent, a status color on its own soft fill, and the
 * accent against the surface it marks (3:1, as a control's edge needs);
 * and the same in the chrome (D-878): its text on it, and its accent on
 * the chrome and on the current item's fill, once a theme sets them.
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
		[AdminToken::Text, AdminToken::SurfaceStripe, 4.5],
		[AdminToken::TextMuted, AdminToken::SurfaceStripe, 3.0],
		[AdminToken::AccentText, AdminToken::Accent, 4.5],
		[AdminToken::Accent, AdminToken::Surface, 3.0],
		[AdminToken::Success, AdminToken::SuccessSubtle, 4.5],
		[AdminToken::Warning, AdminToken::WarningSubtle, 4.5],
		[AdminToken::Danger, AdminToken::DangerSubtle, 4.5],
		[AdminToken::ChromeText, AdminToken::Chrome, 4.5],
		[AdminToken::ChromeTextSecondary, AdminToken::Chrome, 4.5],
		[AdminToken::ChromeTextSecondary, AdminToken::ChromeSubtle, 4.5],
		[AdminToken::ChromeTextMuted, AdminToken::Chrome, 3.0],
		[AdminToken::ChromeAccent, AdminToken::Chrome, 3.0],
		[AdminToken::ChromeAccent, AdminToken::ChromeAccentSubtle, 4.5]
	];

	public function __construct(private Messages $messages)
	{}

	/**
	 * Returns a warning for each pair below its ratio, in either scheme,
	 * given everything a theme shows (Neutral's tokens with its own laid
	 * over them). A color with any transparency isn't judged, since what
	 * shows through it varies. The warnings are messages, in the current
	 * language where they're shown (D-835).
	 *
	 * @return list<Text>
	 */
	public function check(AdminTokens $tokens): array
	{
		$warnings = [];

		foreach (self::PAIRS as [$foreground, $background, $needed]) {
			// A chrome pair the theme sets neither of is a page pair again.
			if ($foreground->fallback() !== null && $background->fallback() !== null && ! $tokens->has($foreground) && ! $tokens->has($background)) {
				continue;
			}

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
					$warnings[] = $this->messages->t(
						'admin.admin_theme_contrast.low',
						'In the {scheme} scheme, {foreground} on {background} is {ratio}:1, under the {needed}:1 it needs.',
						scheme: $scheme->value,
						foreground: $foreground->value,
						background: $background->value,
						ratio: sprintf('%.2f', $ratio),
						needed: rtrim(rtrim(number_format($needed, 1), '0'), '.'),
						context: 'The scheme is light or dark; the foreground and background are token names.'
					);
				}
			}
		}

		return $warnings;
	}
}
