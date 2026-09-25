<?php

/**
 * Theme service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Theme;

use Blush\Core\ServiceProvider;
use Blush\Routing\RouteSource;

/**
 * Binds the installed themes, the per-request theme resolver, and the
 * theme asset route.
 */
final class ThemeServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		Themes::class,
		ThemeResolver::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array TRANSIENTS = [
		ThemeAssetController::class,
		ThemeRoutes::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array TAGS = [
		RouteSource::TAG => [ThemeRoutes::class]
	];
}
