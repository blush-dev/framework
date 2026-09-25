<?php

/**
 * Content page renderer interface.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Turns what a content controller found into a response. The content
 * controllers decide what a URL shows; the renderer decides how it looks.
 * `BasicPageRenderer` is the stand-in until the view layer (M5) binds a
 * themed renderer.
 */
interface PageRenderer
{
	/**
	 * Renders a content page.
	 */
	public function render(ContentPage $page, ServerRequestInterface $request): ResponseInterface;
}
