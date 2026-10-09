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

use Override;
use Blush\Container\ServiceResolver;
use Blush\Core\Paths;
use Blush\Core\ServiceProvider;
use Blush\Routing\RouteSource;

/**
 * Binds the per-request theme resolver, settings,
 * and the theme asset route. `Bootstrap` binds the installed `Themes`
 * (it needs them to register theme providers); an application built
 * without it discovers them on first use.
 */
final class ThemeServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		ThemeResolver::class,
		SettingsResolver::class
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

	/**
	 * Binds the installed themes when `Bootstrap` hasn't.
	 */
	#[Override]
	public function register(): void
	{
		$this->container->singletonIf(
			Themes::class,
			static fn (ServiceResolver $resolver): Themes => new ThemeDiscovery($resolver->make(Paths::class))->discover()
		);
	}
}
