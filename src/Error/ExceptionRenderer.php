<?php

/**
 * Exception renderer interface.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Error;

use Throwable;

/**
 * Turns an uncaught exception into output. Renderers decide how much detail to
 * show; the detailed ones are for development only.
 */
interface ExceptionRenderer
{
	/**
	 * The media type of the rendered output.
	 */
	public function contentType(): string;

	/**
	 * Renders the exception.
	 */
	public function render(Throwable $exception): string;
}
