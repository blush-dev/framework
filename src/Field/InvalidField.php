<?php

/**
 * Invalid field value.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Field;

use InvalidArgumentException;
use Throwable;
use Blush\Content\ContentException;
use Blush\Translation\Text;

/**
 * Thrown by a field when a value doesn't fit it. `Schema` catches it and
 * reports a violation, so one bad value never stops an entry from being
 * indexed. Its message is kept as the `Text` the field wrote (D-836), so
 * the violation can carry it.
 */
final class InvalidField extends InvalidArgumentException implements ContentException
{
	/**
	 * The message as the field wrote it.
	 */
	public readonly string|Text $text;

	public function __construct(string|Text $message, ?Throwable $previous = null)
	{
		$this->text = $message;

		parent::__construct((string) $message, 0, $previous);
	}
}
