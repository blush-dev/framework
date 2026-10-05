<?php

/**
 * Redirect site URLs.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Routing;

use Override;

/**
 * Lists the paths of the route table's redirects without parameters
 * (D-139, D-476). Rendering them asks the site whether each really
 * redirects: a redirect only applies before a 404, so one whose path a
 * page answers is a page, not a redirect. Redirects with parameters
 * can't be listed.
 */
final readonly class RedirectSiteUrls implements UrlSource
{
	public function __construct(private RouteTable $table)
	{}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function urls(): iterable
	{
		foreach ($this->table->redirects() as $redirect) {
			if (! str_contains($redirect->from, '{')) {
				yield new SiteUrl($redirect->from);
			}
		}
	}
}
