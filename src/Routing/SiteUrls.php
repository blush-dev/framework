<?php

/**
 * Site URLs.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Routing;

use Blush\Container\Attributes\Tagged;

/**
 * Gathers every tagged `UrlSource`'s URLs, each path once (D-476). The
 * framework doesn't visit them itself; a plugin does, rendering each
 * through `Kernel::handle()`:
 *
 *     foreach ($urls->all() as $url) {
 *         $response = $kernel->handle(Request::create($origin . $url->path));
 *     }
 */
final readonly class SiteUrls
{
	/**
	 * @param list<UrlSource> $sources
	 */
	public function __construct(
		#[Tagged(UrlSource::TAG)] private array $sources = []
	) {}

	/**
	 * Returns each listed URL, in source order. A path listed twice is
	 * kept the first time.
	 *
	 * @return iterable<SiteUrl>
	 */
	public function all(): iterable
	{
		$seen = [];

		foreach ($this->sources as $source) {
			foreach ($source->urls() as $url) {
				if (! isset($seen[$url->path])) {
					$seen[$url->path] = true;

					yield $url;
				}
			}
		}
	}
}
