<?php

/**
 * Setup check result.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Setup;

use Blush\Translation\Text;

/**
 * One setup check's outcome: what was checked, what was found, and, when
 * something's wrong, what to do about it. Its words are `Text` from
 * `t()` (D-836), so Site Health can keep them as messages and show them
 * in its reader's language; names (a path, a setting, an extension) are
 * plain strings.
 */
final readonly class CheckResult
{
	public function __construct(
		public CheckStatus $status,
		public string|Text $label,
		public string|Text $message,
		public string|Text $hint = ''
	) {}

	/**
	 * A check that passed.
	 */
	public static function pass(string|Text $label, string|Text $message): self
	{
		return new self(CheckStatus::Pass, $label, $message);
	}

	/**
	 * A problem worth fixing that doesn't stop the site.
	 */
	public static function warning(string|Text $label, string|Text $message, string|Text $hint = ''): self
	{
		return new self(CheckStatus::Warning, $label, $message, $hint);
	}

	/**
	 * A problem that stops the site from working.
	 */
	public static function failure(string|Text $label, string|Text $message, string|Text $hint = ''): self
	{
		return new self(CheckStatus::Failure, $label, $message, $hint);
	}

	/**
	 * Whether the check failed.
	 */
	public function isFailure(): bool
	{
		return $this->status === CheckStatus::Failure;
	}
}
