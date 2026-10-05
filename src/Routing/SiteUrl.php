<?php

/**
 * Site URL.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Routing;

use Closure;

/**
 * A URL path the site serves (D-476). A listing gives `$page`, which
 * returns the path of a later page (or `null`): a visitor asks for page
 * 2, 3, and so on until one isn't a 200, so the number of pages always
 * matches what the controller serves.
 *
 *     new SiteUrl('/about');
 *     new SiteUrl('/topics', fn (int $page): ?string => $urls->collection($topics, $page));
 */
final readonly class SiteUrl
{
	/**
	 * @param ?Closure(int): ?string $page
	 */
	public function __construct(
		public string $path,
		public ?Closure $page = null
	) {}

	/**
	 * Returns the path of a later page, or `null` when the URL isn't paged.
	 */
	public function page(int $number): ?string
	{
		return $this->page === null ? null : ($this->page)($number);
	}
}
