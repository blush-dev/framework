<?php

/**
 * Content benchmarks.
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
use Blush\Content\ContentRepository;
use Blush\Content\Index\Indexer;
use Blush\Content\Index\PhpIndex;
use Blush\Content\Query\Order;
use Blush\Core\Application;
use Blush\Core\Bootstrap;
use Blush\Core\Paths;
use Blush\Http\Kernel;
use Blush\Http\Request;

/**
 * Indexing, queries, and whole requests against the jtcom-sized site
 * (D-044). Each iteration boots a fresh application with the index
 * already built, so a subject measures its own work: the index snapshot
 * is loaded on a subject's first use and then shared, as within one
 * request. Caching is off, so the request subjects measure rendering;
 * `CacheBench` measures the caches.
 */
#[Bench\BeforeMethods('setUp')]
#[Bench\Warmup(1)]
#[Bench\Iterations(5)]
#[Bench\Revs(50)]
#[Bench\OutputTimeUnit('milliseconds', precision: 3)]
final class ContentBench
{
	private Application $app;

	private ContentRepository $content;

	public function setUp(): void
	{
		$this->app = new Bootstrap(Paths::fromRoot(JtcomSizedSite::root()))->createApplication();
		$this->app->container()->instance(CacheConfig::class, new CacheConfig(enabled: false));
		$this->app->boot();

		$indexer = $this->app->container()->make(Indexer::class);
		$indexer->index();

		$this->content = $this->app->container()->make(ContentRepository::class);
		$this->content->findPath('index.md');
	}

	#[Bench\Revs(1)]
	#[Bench\Subject]
	public function benchIndexFull(): void
	{
		$this->app->container()->make(Indexer::class)->index(full: true);
	}

	#[Bench\Revs(5)]
	#[Bench\Subject]
	public function benchIndexUnchanged(): void
	{
		$this->app->container()->make(Indexer::class)->index();
	}

	#[Bench\Revs(5)]
	#[Bench\Subject]
	public function benchLoadIndex(): void
	{
		new PhpIndex(Paths::fromRoot(JtcomSizedSite::root()))->snapshot();
	}

	#[Bench\Subject]
	public function benchHomePage(): void
	{
		$this->content->query()->type('post')->orderBy('published', Order::Desc)->paginate(10)->all();
	}

	#[Bench\Subject]
	public function benchDeepPage(): void
	{
		$this->content->query()->type('post')->orderBy('published', Order::Desc)->paginate(10, 80)->all();
	}

	#[Bench\Subject]
	public function benchDateArchive(): void
	{
		$this->content->query()->type('post')->date(year: 2010, month: 6)->orderBy('published', Order::Desc)->paginate(10)->all();
	}

	#[Bench\Subject]
	public function benchTermArchive(): void
	{
		$this->content->query()->type('post')->whereTerm('category', 'topic-7')->orderBy('filename', Order::Desc)->paginate(10)->all();
	}

	#[Bench\Subject]
	public function benchNamedLookup(): void
	{
		$this->content->named('post', JtcomSizedSite::postSlug(500));
	}

	#[Bench\Subject]
	public function benchTermCounts(): void
	{
		$this->content->termCounts('category');
	}

	#[Bench\Revs(20)]
	#[Bench\Subject]
	public function benchRequestHome(): void
	{
		$this->app->container()->make(Kernel::class)->handle(Request::create('/'));
	}

	#[Bench\Revs(20)]
	#[Bench\Subject]
	public function benchRequestSingle(): void
	{
		$this->app->container()->make(Kernel::class)->handle(Request::create('/topics/topic-7'));
	}
}
