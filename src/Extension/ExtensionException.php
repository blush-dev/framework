<?php

/**
 * Extension exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Extension;

use RuntimeException;
use Throwable;
use Blush\Core\BlushException;
use Blush\Translation\Text;

/**
 * Thrown when an extension manifest is missing or invalid, two extensions
 * share a name, or config enables an extension that doesn't exist.
 */
final class ExtensionException extends RuntimeException implements BlushException
{
	/**
	 * Its message as written with `t()`, when it was, so what keeps the
	 * reason to show later (a compiled cache) keeps the message rather
	 * than the sentence (D-836); its message is that, in the current
	 * language.
	 */
	public readonly ?Text $text;

	public function __construct(string|Text $message = '', int $code = 0, ?Throwable $previous = null)
	{
		parent::__construct((string) $message, $code, $previous);

		$this->text = $message instanceof Text ? $message : null;
	}

	/**
	 * Returns its reason to keep: its message as written, else its text.
	 */
	public function reason(): string|Text
	{
		return $this->text ?? $this->getMessage();
	}
}
