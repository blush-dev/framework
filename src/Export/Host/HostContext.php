<?php

/**
 * Host context.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Export\Host;

use Blush\Export\ExportRedirect;

/**
 * What host files describe about an export:
 *
 * - `redirects`, in precedence order (the first match wins);
 * - `indexes`: URL paths served by an index file that isn't
 *   `index.html`, with the file and its content type
 *   (`/feed` => `['feed/index.rss', 'application/rss+xml']`);
 * - `trailingSlash`: whether canonical URLs end in `/`
 *   (`RouteConfig::$trailingSlash`);
 * - `notFound`: the 404 page's file, if the export has one.
 */
final readonly class HostContext
{
	/**
	 * @param list<ExportRedirect>                  $redirects
	 * @param array<string, array{string, string}> $indexes
	 */
	public function __construct(
		public array $redirects = [],
		public array $indexes = [],
		public bool $trailingSlash = false,
		public ?string $notFound = null
	) {}
}
