<?php

/**
 * Document renderer.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View;

use Psr\Http\Message\ServerRequestInterface;
use Blush\Theme\ThemeException;
use Blush\Theme\ThemeResolver;

/**
 * Renders a themed document that isn't an HTML page, such as a feed or a
 * sitemap (D-029): the first view in a hierarchy, through the request's
 * theme chain, with no layout or head.
 */
final readonly class DocumentRenderer
{
	public function __construct(
		private ThemeResolver $themes,
		private ViewFactory $views
	) {}

	/**
	 * Renders the first view that exists.
	 *
	 * @param  list<string>         $names
	 * @param  array<string, mixed> $data
	 * @throws ThemeException
	 * @throws ViewException
	 */
	public function render(ServerRequestInterface $request, array $names, array $data): string
	{
		return $this->views->forChain($this->themes->forRequest($request))->render($names, $data, $this->views->fragment());
	}
}
