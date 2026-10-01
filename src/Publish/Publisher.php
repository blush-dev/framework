<?php

/**
 * Publisher.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Publish;

use Blush\Cache\CacheException;
use Blush\Cache\Caches;
use Blush\Cache\ContentVersion;
use Blush\Content\Index\IndexException;
use Blush\Content\Index\Indexer;
use Blush\Media\Index\MediaIndexer;
use Blush\Content\Source\UnreadableSource;
use Blush\Content\Type\ContentTypeCache;
use Blush\Content\Type\InvalidContentType;
use Blush\Core\AppConfig;
use Blush\Core\Paths;
use Blush\Event\Dispatcher;
use Blush\Publish\Events\ContentPublished;
use Blush\Routing\InvalidRoute;
use Blush\Routing\RouteCache;

/**
 * Puts the site's current files live (D-013): the one thing `publish`,
 * the webhook, and (later) the admin all do.
 *
 * 1. With `PublishConfig::$git` (or when asked), `git pull` in `user/`.
 *    A failed pull stops here, and nothing changes.
 * 2. Outside development, rewrite the compiled content types and route
 *    table if they exist, so `user/data/types` and data-file redirects
 *    take effect (D-097). A change to the types makes the next request
 *    rebuild the index (D-098).
 * 3. Reindex content incrementally, then media (D-288).
 * 4. Clear the cache store and move the content version on.
 * 5. Dispatch `ContentPublished`.
 *
 * Only one publish runs at a time (`storage/cache/publish.lock`).
 */
final readonly class Publisher
{
	public function __construct(
		private PublishConfig $config,
		private Paths $paths,
		private AppConfig $app,
		private Puller $puller,
		private Indexer $indexer,
		private MediaIndexer $media,
		private ContentTypeCache $types,
		private RouteCache $routes,
		private Caches $caches,
		private ContentVersion $version,
		private Dispatcher $events
	) {}

	/**
	 * Publishes. `$pull` overrides `PublishConfig::$git`.
	 *
	 * @throws PublishInProgress When another publish is running.
	 * @throws IndexException
	 * @throws UnreadableSource
	 * @throws InvalidContentType
	 * @throws InvalidRoute
	 * @throws CacheException
	 */
	public function publish(?bool $pull = null): PublishReport
	{
		$start = hrtime(true);
		$lock  = $this->lock();

		try {
			$pulled = ($pull ?? $this->config->git) ? $this->puller->pull($this->paths->user) : null;

			if ($pulled !== null && ! $pulled->successful) {
				return new PublishReport(pull: $pulled, milliseconds: self::since($start));
			}

			$compiled = ! $this->app->environment->isDevelopment();
			$types    = $compiled && is_file($this->types->path());

			if ($types) {
				$this->types->write();
			}

			$index  = $this->indexer->index();
			$media  = $this->media->index();
			$routes = $compiled && is_file($this->routes->path());

			if ($routes) {
				$this->routes->write();
			}

			$cleared = $this->caches->clear();
			$this->caches->prune();

			$report = new PublishReport(
				pull: $pulled,
				index: $index,
				media: $media,
				routes: $routes,
				types: $types,
				cleared: $cleared,
				version: $this->version->bump(),
				milliseconds: self::since($start)
			);
		} finally {
			flock($lock, LOCK_UN);
			fclose($lock);
		}

		$this->events->dispatch(new ContentPublished($report));

		return $report;
	}

	/**
	 * Takes the publish lock.
	 *
	 * @return resource
	 * @throws PublishInProgress
	 */
	private function lock(): mixed
	{
		if (! is_dir($this->paths->cache)) {
			@mkdir($this->paths->cache, 0775, true);
		}

		$lock = @fopen("{$this->paths->cache}/publish.lock", 'c');

		if ($lock === false) {
			throw new PublishInProgress(sprintf('Unable to open the publish lock in "%s".', $this->paths->cache));
		}

		if (! flock($lock, LOCK_EX | LOCK_NB)) {
			fclose($lock);

			throw new PublishInProgress('Another publish is running.');
		}

		return $lock;
	}

	/**
	 * Returns the milliseconds since a `hrtime()` reading.
	 */
	private static function since(int $start): int
	{
		return intdiv(hrtime(true) - $start, 1_000_000);
	}
}
