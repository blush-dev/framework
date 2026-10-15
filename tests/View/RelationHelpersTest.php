<?php

/**
 * Relation helpers tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Http\Kernel;
use Blush\Http\Request;
use Blush\Tests\Content\BuildsContentSite;
use Blush\Tests\WritesThemeViews;
use Blush\View\Template;

#[CoversClass(Template::class)]
final class RelationHelpersTest extends TestCase
{
	use BuildsContentSite;
	use WritesThemeViews;

	protected function setUp(): void
	{
		$this->contentConfig([
			'types'     => [
				'post'   => ['routing' => ['prefix' => 'archives']],
				'movie'  => ['routing' => ['prefix' => 'movies']],
				'person' => ['routing' => ['prefix' => 'people']]
			],
			'relations' => [
				'related' => ['kind' => 'reference', 'from' => ['post'], 'to' => ['post'], 'symmetric' => true],
				'actors'  => ['kind' => 'reference', 'from' => ['movie'], 'to' => ['person'], 'ordered' => true]
			]
		]);

		$this->entry('_post/a.md', "title: A\ndate: 2026-01-01\nrelated: [b]");
		$this->entry('_post/b.md', "title: B\ndate: 2026-01-02");
		$this->entry('_post/c.md', "title: C\ndate: 2026-01-03\nrelated: [b, d]");
		$this->entry('_post/d.md', "title: D\ndate: 2026-01-04\nstatus: draft");
		$this->entry('_movie/big.md', "title: Big\ndate: 2026-01-01\nactors: [tom, meg]");
		$this->entry('_movie/splash.md', "title: Splash\ndate: 2026-02-01\nactors: [tom]");
		$this->entry('_person/tom.md', 'title: Tom');
		$this->entry('_person/meg.md', 'title: Meg');

		$this->themeView('single-post.php', '<?= e(implode(", ", array_map(fn ($post) => $post->title, $entry->related("related")->all()))) ?>');
		$this->themeView('single-movie.php', '<?= e(implode(", ", array_map(fn ($person) => $person->title, $entry->related("actors")->all()))) ?>');
		$this->themeView('single-person.php', '<?= e(implode(", ", array_map(fn ($movie) => $movie->title, $entry->referencedBy("movie.actors")->all()))) ?>');
	}

	private function body(string $uri): string
	{
		return (string) $this->site()->container()->make(Kernel::class)->handle(Request::create($uri))->getBody();
	}

	public function testTemplatesListAnEntrysLinksBothWays(): void
	{
		$this->assertSame('Tom, Meg', $this->body('/movies/big'), 'In the order front matter lists them.');
		$this->assertSame('Splash, Big', $this->body('/people/tom'), 'What links to it, newest first (D-596).');
		$this->assertSame('C, A', $this->body('/archives/b'), 'A symmetric relation answers from both ends.');
		$this->assertSame('B, D', $this->body('/archives/c'), 'Drafts too: what shows them decides (D-802).');
	}
}
