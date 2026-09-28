<?php

/**
 * Region render.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Region;

use Blush\View\ViewContext;
use Blush\View\Views;

/**
 * What a region item renders with: the theme chain's views, the page's
 * context, and the locale its text is picked in (D-202).
 */
final readonly class RegionRender
{
	public function __construct(
		public Views $views,
		public ViewContext $context,
		public string $locale,
		public string $defaultLocale
	) {}
}
