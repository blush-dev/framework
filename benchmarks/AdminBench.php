<?php

/**
 * Admin benchmarks.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Benchmarks;

use PhpBench\Attributes as Bench;
use Blush\Admin\EntriesController;
use Blush\Auth\Account;
use Blush\Benchmarks\Fixture\JtcomSizedSite;
use Blush\Content\ContentRepository;
use Blush\Content\Index\Indexer;
use Blush\Storage\Record\Order;
use Blush\Core\Application;
use Blush\Core\Bootstrap;
use Blush\Core\Paths;
use Blush\Http\Request;

/**
 * The admin's entry list (D-230) against the jtcom-sized site. The
 * permission rules, filters, and paging run in the index as one query;
 * `benchIndexPage` is a plain query for the same first page, for
 * comparison. The controller is called directly, so sessions and
 * routing aren't measured. As in `ContentBench`, each iteration boots a
 * fresh application with the index already built and loaded.
 */
#[Bench\BeforeMethods('setUp')]
#[Bench\Warmup(1)]
#[Bench\Iterations(5)]
#[Bench\Revs(20)]
#[Bench\OutputTimeUnit('milliseconds', precision: 3)]
final class AdminBench
{
	private Application $app;

	private EntriesController $controller;

	private Account $editor;

	private Account $author;

	private Account $contributor;

	public function setUp(): void
	{
		$this->app = new Bootstrap(Paths::fromRoot(JtcomSizedSite::root()))->createApplication();
		$this->app->boot();
		$this->app->container()->make(Indexer::class)->index();
		$this->app->container()->make(ContentRepository::class)->findPath('index.md');

		$this->controller  = $this->app->container()->make(EntriesController::class);
		$this->editor      = new Account('editor', '', ['editor']);
		$this->author      = new Account('author', '', ['author'], 'justintadlock');
		$this->contributor = new Account('contributor', '', ['contributor'], 'justintadlock');
	}

	#[Bench\Subject]
	public function benchListAll(): void
	{
		$this->list($this->editor);
	}

	#[Bench\Subject]
	public function benchListDeepPage(): void
	{
		$this->list($this->editor, ['page' => '40']);
	}

	#[Bench\Subject]
	public function benchListPublishedPosts(): void
	{
		$this->list($this->editor, ['type' => 'post', 'status' => 'published']);
	}

	#[Bench\Subject]
	public function benchListSearch(): void
	{
		$this->list($this->editor, ['search' => 'garden']);
	}

	#[Bench\Subject]
	public function benchListAuthor(): void
	{
		$this->list($this->author);
	}

	#[Bench\Subject]
	public function benchListContributor(): void
	{
		$this->list($this->contributor);
	}

	#[Bench\Subject]
	public function benchIndexPage(): void
	{
		$this->app->container()->make(ContentRepository::class)->query()->any()->orderBy('updated', Order::Desc)->paginate(EntriesController::PER_PAGE)->all();
	}

	/**
	 * Asks the controller for a page of the list.
	 *
	 * @param array<string, string> $query
	 */
	private function list(Account $account, array $query = []): void
	{
		($this->controller)(Request::create('https://bench.example/admin/api/entries')->withQueryParams($query)->withAttribute(Account::class, $account));
	}
}
