<?php

/**
 * Redirect export URLs.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Routing;

use Override;
use Blush\Export\ExportUrl;
use Blush\Export\UrlSource;

/**
 * Lists the paths of the route table's redirects without parameters, for
 * static export (D-139). Rendering them asks the site whether each
 * really redirects: a redirect only applies before a 404, so one whose
 * path a page answers is a page in the export, not a redirect. Redirects
 * with parameters can't be listed; host files carry them as patterns.
 */
final readonly class RedirectExportUrls implements UrlSource
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
				yield new ExportUrl($redirect->from);
			}
		}
	}
}
