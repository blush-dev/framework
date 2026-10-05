<?php

/**
 * Export site.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Export;

use Psr\Clock\ClockInterface;
use Blush\Cache\CacheConfig;
use Blush\Cache\CacheDriver;
use Blush\Core\AppConfig;
use Blush\Core\Application;
use Blush\Core\Bootstrap;
use Blush\Core\Environment;
use Blush\Core\Paths;

/**
 * Boots the application static export renders with (D-135): the site as
 * production serves it, whatever environment the command runs in, so
 * drafts, `?theme=`, and development's `robots.txt` never reach the
 * output.
 *
 * - `AppConfig` gets the export's origin as `url` and the production
 *   environment.
 * - Caching is on, in memory only (`array`), without the page cache: a
 *   body rendered for its page is reused by the listings and feeds that
 *   show it, and the site's own cache store is left alone.
 * - Compiled caches live in `storage/cache/export`, which nothing
 *   compiles into, so config, routes, content types, themes, and
 *   extensions are always read fresh from the site's files.
 * - It shares the site's clock, so both agree on what's scheduled.
 */
final readonly class ExportSite
{
	/**
	 * The folder under the cache path the export application uses.
	 */
	public const string CACHE = 'export';

	public function __construct(
		private Bootstrap $bootstrap,
		private Paths $paths,
		private AppConfig $app,
		private ClockInterface $clock
	) {}

	/**
	 * Boots the export application for an origin.
	 */
	public function boot(string $url): Application
	{
		$paths = Paths::fromArray([...$this->paths->toArray(), 'cache' => $this->cachePath()]);

		$application = $this->bootstrap->withPaths($paths)->withConfig(
			new AppConfig(
				name: $this->app->name,
				url: $url,
				environment: Environment::Production,
				debug: $this->app->debug,
				timezone: $this->app->timezone,
				locale: $this->app->locale,
				providers: $this->app->providers,
				languages: $this->app->languages->toArray()
			),
			new CacheConfig(enabled: true, driver: CacheDriver::Array->value, pages: false)
		)->createApplication();

		$application->container()->instance(ClockInterface::class, $this->clock);
		$application->boot();

		return $application;
	}

	/**
	 * Returns the export application's cache folder.
	 */
	public function cachePath(): string
	{
		return $this->paths->cache . '/' . self::CACHE;
	}
}
