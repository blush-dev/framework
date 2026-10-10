<?php

/**
 * Outcome.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console;

use Blush\Field\Severity;

/**
 * How something a command checked or did turned out: a check row's mark
 * (`Output::status()`) and a summary's color (`Output::summary()`). On a
 * terminal it's a symbol; elsewhere, a word (D-717).
 */
enum Outcome
{
	case Pass;
	case Notice;
	case Warning;
	case Failure;

	/**
	 * The worst of several outcomes, or a pass when there are none. A
	 * notice is no worse than a pass.
	 */
	public static function worst(self ...$outcomes): self
	{
		return match (true) {
			in_array(self::Failure, $outcomes, true) => self::Failure,
			in_array(self::Warning, $outcomes, true) => self::Warning,
			default                                  => self::Pass
		};
	}

	/**
	 * The outcome of a problem a check found.
	 */
	public static function of(Severity $severity): self
	{
		return match ($severity) {
			Severity::Error   => self::Failure,
			Severity::Warning => self::Warning,
			Severity::Notice  => self::Notice
		};
	}

	/**
	 * The symbol drawn on a terminal.
	 */
	public function symbol(): string
	{
		return match ($this) {
			self::Pass    => '✓',
			self::Notice  => 'i',
			self::Warning => '!',
			self::Failure => '✗'
		};
	}

	/**
	 * The word written off a terminal.
	 */
	public function word(): string
	{
		return match ($this) {
			self::Pass    => 'ok',
			self::Notice  => 'notice',
			self::Warning => 'warning',
			self::Failure => 'error'
		};
	}

	/**
	 * The tone it's drawn in.
	 */
	public function tone(): Tone
	{
		return match ($this) {
			self::Pass    => Tone::Pass,
			self::Notice  => Tone::Muted,
			self::Warning => Tone::Warning,
			self::Failure => Tone::Failure
		};
	}
}
