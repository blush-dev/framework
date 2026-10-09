<?php

/**
 * SQLite content benchmarks.
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
use Blush\Content\Entries;
use Blush\Core\Application;
use Blush\Core\Bootstrap;
use Blush\Core\Paths;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Storage\Record\Order;
use Blush\Storage\StorageConfig;
use Blush\Storage\StorageCopy;

/**
 * `ContentBench`'s queries and requests on the SQLite driver (D-662), the
 * jtcom-sized site copied once into its database (`storage:copy`), so the
 * two drivers compare subject by subject. As there, each iteration boots
 * a fresh application.
 */
#[Bench\BeforeMethods('setUp')]
#[Bench\Warmup(1)]
#[Bench\Iterations(5)]
#[Bench\Revs(50)]
#[Bench\OutputTimeUnit('milliseconds', precision: 3)]
final class SqliteContentBench
{
	private Application $app;

	private Entries $content;

	public function setUp(): void
	{
		$root = JtcomSizedSite::root();

		if (! is_file($root . '/' . StorageConfig::SQLITE_FILE)) {
			$files = new Bootstrap(Paths::fromRoot($root))->createApplication();
			$files->boot();
			$files->container()->make(StorageCopy::class)->copy(StorageConfig::FILESYSTEM, StorageConfig::SQLITE);
		}

		$this->app = new Bootstrap(Paths::fromRoot($root), ['STORAGE_DRIVER' => StorageConfig::SQLITE])->createApplication();
		$this->app->container()->instance(CacheConfig::class, new CacheConfig(enabled: false));
		$this->app->boot();

		$this->content = $this->app->container()->make(Entries::class);
		$this->content->named('page', '');
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
