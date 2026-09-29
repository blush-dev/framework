<?php

/**
 * Admin action result.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin\Action;

/**
 * How an action went: whether it worked, a sentence for the person who
 * ran it, and optional details (a list of lines, such as files that
 * couldn't be indexed).
 */
final readonly class ActionResult
{
	/**
	 * @param list<string> $details
	 */
	public function __construct(
		public bool $successful,
		public string $message,
		public array $details = []
	) {}

	/**
	 * An action that worked.
	 *
	 * @param list<string> $details
	 */
	public static function success(string $message, array $details = []): self
	{
		return new self(true, $message, $details);
	}

	/**
	 * An action that failed.
	 *
	 * @param list<string> $details
	 */
	public static function failure(string $message, array $details = []): self
	{
		return new self(false, $message, $details);
	}

	/**
	 * Returns the result as plain data.
	 *
	 * @return array{successful: bool, message: string, details: list<string>}
	 */
	public function toArray(): array
	{
		return ['successful' => $this->successful, 'message' => $this->message, 'details' => $this->details];
	}
}
