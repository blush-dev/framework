<?php

/**
 * Output palette.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console;

use Closure;

/**
 * The colors each `Tone` is drawn in (D-718). `Basic` is the terminal's
 * own 16 colors, which follow its theme, so they're readable on any
 * background. `Dark` and `Light` are the project's colors (x3p0.com's,
 * each tuned for its background), drawn only where the terminal shows
 * exact colors and says what its background is.
 */
enum Palette
{
	case Basic;
	case Dark;
	case Light;

	/**
	 * The palette for a terminal: the project's colors when
	 * `COLORTERM` says it shows exact colors and its background is known,
	 * from `COLORFGBG` or else from asking (`$ask` returns the terminal's
	 * reply to OSC 11, or null); otherwise its own.
	 *
	 * @param array<string, string>  $env
	 * @param ?Closure(): ?string    $ask
	 */
	public static function detect(array $env, ?Closure $ask = null): self
	{
		if (! in_array(strtolower($env['COLORTERM'] ?? ''), ['truecolor', '24bit'], true)) {
			return self::Basic;
		}

		$known = isset($env['COLORFGBG']) ? self::fromColorFgBg($env['COLORFGBG']) : null;
		$reply = $known === null && $ask !== null ? $ask() : null;

		return $known ?? ($reply === null ? null : self::fromReply($reply)) ?? self::Basic;
	}

	/**
	 * Reads `COLORFGBG` (`fg;bg`, the background last), which some
	 * terminals set: a background of 0 to 6 or 8 is dark, 7 or 9 to 15
	 * light.
	 */
	public static function fromColorFgBg(string $value): ?self
	{
		$background = (string) strrchr(";{$value}", ';') |> (static fn (string $last): string => substr($last, 1));

		return match (true) {
			! ctype_digit($background) || (int) $background > 15 => null,
			in_array((int) $background, [7, ...range(9, 15)], true) => self::Light,
			default => self::Dark
		};
	}

	/**
	 * Reads a terminal's reply to OSC 11 (`]11;rgb:RRRR/GGGG/BBBB`, with
	 * one to four hex digits a channel): dark when its luminance is
	 * under half.
	 */
	public static function fromReply(string $reply): ?self
	{
		if (preg_match('#\]11;rgba?:([0-9a-f]{1,4})/([0-9a-f]{1,4})/([0-9a-f]{1,4})#i', $reply, $matches) !== 1) {
			return null;
		}

		[$red, $green, $blue] = array_map(
			static fn (string $channel): float => hexdec($channel) / (16 ** strlen($channel) - 1),
			[$matches[1], $matches[2], $matches[3]]
		);

		return 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue < 0.5 ? self::Dark : self::Light;
	}

	/**
	 * Returns the SGR codes that draw a tone.
	 */
	public function codes(Tone $tone): string
	{
		if ($tone === Tone::Heading) {
			return (string) Style::Bold->value;
		}

		if ($this === self::Basic) {
			return (string) match ($tone) {
				Tone::Code    => Style::Cyan->value,
				Tone::Muted   => Style::Dim->value,
				Tone::Pass    => Style::Green->value,
				Tone::Warning => Style::Yellow->value,
				Tone::Failure => Style::Red->value
			};
		}

		$dark = $this === self::Dark;
		$hex  = match ($tone) {
			Tone::Code    => $dark ? '4fd8ff' : '0369a1',
			Tone::Muted   => $dark ? '7d89ab' : '5f6c88',
			Tone::Pass    => $dark ? '34d399' : '0f766e',
			Tone::Warning => $dark ? 'ffb454' : 'a35a00',
			Tone::Failure => $dark ? 'ff6b81' : 'be123c'
		};

		return '38;2;' . implode(';', array_map(hexdec(...), str_split($hex, 2)));
	}
}
