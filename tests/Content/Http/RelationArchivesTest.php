<?php

/**
 * Relation archives tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Content\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Blush\Content\Http\RelatedController;
use Blush\Content\Http\RelatedListController;
use Blush\Content\Http\TermController;
use Blush\Content\RelationArchives;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Tests\Content\BuildsContentSite;

#[CoversClass(TermController::class)]
#[CoversClass(RelatedController::class)]
#[CoversClass(RelatedListController::class)]
#[CoversClass(RelationArchives::class)]
final class RelationArchivesTest extends TestCase
{
	use BuildsContentSite;

	protected function setUp(): void
	{
		$this->contentConfig([
			'types'     => [
				'movie'  => ['path' => '_movies', 'routing' => ['prefix' => 'movies'], 'feed' => true],
				'person' => ['path' => '_people', 'routing' => ['prefix' => 'people'], 'feed' => true]
			],
			'relations' => [
				'actors'    => ['kind' => 'reference', 'from' => ['movie'], 'to' => ['person'], 'inverse' => ['page' => true]],
				'directors' => ['kind' => 'reference', 'from' => ['movie'], 'to' => ['person'], 'inverse' => ['archive' => 'directors'], 'label' => 'Directors']
			]
		]);

		$this->entry('_movies/big.md', "title: Big\npublished: 2026-01-01\nactors: [tom]\ndirectors: penny");
		$this->entry('_movies/splash.md', "title: Splash\npublished: 2026-02-01\nactors: [tom, daryl]\ndirectors: ron");
		$this->entry('_movies/draft.md', "title: Draft\npublished: 2026-03-01\nstatus: draft\ndirectors: tom");
		$this->entry('_people/tom.md', 'title: Tom');
		$this->entry('_people/daryl.md', 'title: Daryl');
		$this->entry('_people/penny.md', 'title: Penny');
		$this->entry('_people/ron.md', 'title: Ron');

		$list = '<?= e(implode(", ", array_map(fn ($item) => $item->title, $entries->all()))) ?>';

		$this->writeTemporaryFile('resources/views/term.php', "term {$list}");
		$this->writeTemporaryFile('resources/views/related.php', "related <?= e(\$title) ?>: {$list}");
		$this->writeTemporaryFile('resources/views/related-list.php', "list <?= e(\$title) ?>: {$list}");
	}

	private function get(string $uri): ResponseInterface
	{
		return $this->site()->container()->make(Kernel::class)->handle(Request::create($uri));
	}

	public function testArchiveTrueListsWhatLinksToAnEntryOnItsPage(): void
	{
		$this->assertSame('term Splash, Big', (string) $this->get('/people/tom')->getBody(), 'A movie\'s actors are listed on each person\'s page (D-596).');
		$this->assertSame('term Splash', (string) $this->get('/people/daryl')->getBody());
		$this->assertSame(200, $this->get('/people/tom/feed')->getStatusCode(), 'With a feed.');
		$this->assertSame('term ', (string) $this->get('/people/penny')->getBody(), 'A person no actors relation names still has a page; directors have archives of their own.');
	}

	public function testAWordGivesArchivesUnderTheSourceType(): void
	{
		$this->assertSame('related Penny: Big', (string) $this->get('/movies/directors/penny')->getBody());
		$this->assertSame('list Directors: Penny, Ron', (string) $this->get('/movies/directors')->getBody(), 'Every target a published movie links to, by title.');
		$this->assertSame(404, $this->get('/movies/directors/tom')->getStatusCode(), 'Only a draft names Tom.');
		$this->assertSame(404, $this->get('/movies/directors/nobody')->getStatusCode());
		$this->assertSame(200, $this->get('/movies/directors/penny/feed')->getStatusCode());
		$this->assertStringContainsString('<title>Penny | Directors | Movies | Blush</title>', (string) $this->get('/movies/directors/penny/feed/atom')->getBody());

		$sitemap = (string) $this->get('/sitemap/movie')->getBody();

		$this->assertStringContainsString('/movies/directors/penny</loc>', $sitemap);
		$this->assertStringContainsString('/movies/directors</loc>', $sitemap);
	}
}
