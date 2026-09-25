<?php

/**
 * Error pages interface.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Http;

use Throwable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Renders the site's own page for an error response, such as a themed
 * 404. `HandleErrors` asks it first and falls back to the generic
 * `ExceptionRenderer` page when it returns `null` or fails, so an error
 * page can never hide the error it reports. The view layer binds
 * `View\ThemedErrorPages`.
 */
interface ErrorPages
{
	/**
	 * Returns a response for an error with its status, or `null` to use
	 * the generic page.
	 */
	public function render(Throwable $error, Status $status, ServerRequestInterface $request): ?ResponseInterface;
}
