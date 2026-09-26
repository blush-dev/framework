<?php

/**
 * Cache benchmarks.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Benchmarks;

use PhpBench\Attributes as Bench;
use Blush\Benchmarks\Fixture\JtcomSizedSite;
use Blush\Cache\CacheConfig;
use Blush\Content\Index\Indexer;
use Blush\Core\Application;
use Blush\Core\Bootstrap;
use Blush\Core\Paths;
use Blush\Http\Kernel;
use Blush\Http\Request;

/**
 * Whole requests against the jtcom-sized site with the caches warm
 * (M6): with the page cache off, so pages render but reuse cached
 * bodies and token CSS, and with it on, so a request is a page cache
 * hit. Compare with `ContentBench`'s uncached request subjects.
 */
#[Bench\BeforeMethods('setUp')]
#[Bench\Warmup(1)]
#[Bench\Iterations(5)]
#[Bench\Revs(50)]
#[Bench\OutputTimeUnit('milliseconds', precision: 3)]
final class CacheBench
{
	private Application $bodies;

	private Application $pages;

	public function setUp(): void
	{
		$this->bodies = $this->boot(new CacheConfig(pages: false));
		$this->pages  = $this->boot(new CacheConfig());

		foreach (['/', '/topics/topic-7'] as $path) {
			$this->request($this->bodies, $path);
			$this->request($this->pages, $path);
		}
	}

	#[Bench\Subject]
	public function benchRequestHomeCachedBodies(): void
	{
		$this->request($this->bodies, '/');
	}

	#[Bench\Subject]
	public function benchRequestSingleCachedBodies(): void
	{
		$this->request($this->bodies, '/topics/topic-7');
	}

	#[Bench\Subject]
	public function benchRequestHomeCachedPage(): void
	{
		$this->request($this->pages, '/');
	}

	#[Bench\Subject]
	public function benchRequestSingleCachedPage(): void
	{
		$this->request($this->pages, '/topics/topic-7');
	}

	/**
	 * Boots the site with a cache config, with the index built.
	 */
	private function boot(CacheConfig $config): Application
	{
		$app = new Bootstrap(Paths::fromRoot(JtcomSizedSite::root()))->createApplication();
		$app->container()->instance(CacheConfig::class, $config);
		$app->boot();
		$app->container()->make(Indexer::class)->index();

		return $app;
	}

	/**
	 * Handles a request.
	 */
	private function request(Application $app, string $path): void
	{
		$app->container()->make(Kernel::class)->handle(Request::create($path));
	}
}
