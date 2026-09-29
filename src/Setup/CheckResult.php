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

/**
 * One setup check's outcome: what was checked, what was found, and, when
 * something's wrong, what to do about it.
 */
final readonly class CheckResult
{
	public function __construct(
		public CheckStatus $status,
		public string $label,
		public string $message,
		public string $hint = ''
	) {}

	/**
	 * A check that passed.
	 */
	public static function pass(string $label, string $message): self
	{
		return new self(CheckStatus::Pass, $label, $message);
	}

	/**
	 * A problem worth fixing that doesn't stop the site.
	 */
	public static function warning(string $label, string $message, string $hint = ''): self
	{
		return new self(CheckStatus::Warning, $label, $message, $hint);
	}

	/**
	 * A problem that stops the site from working.
	 */
	public static function failure(string $label, string $message, string $hint = ''): self
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
