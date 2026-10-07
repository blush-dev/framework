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
use Blush\View\Template;

#[CoversClass(Template::class)]
final class RelationHelpersTest extends TestCase
{
	use BuildsContentSite;

	protected function setUp(): void
	{
		$this->contentConfig([
			'types'     => [
				'post'   => ['path' => '_posts', 'people' => false, 'routing' => ['prefix' => 'archives']],
				'movie'  => ['path' => '_movies', 'people' => false, 'routing' => ['prefix' => 'movies']],
				'person' => ['path' => '_people', 'people' => false, 'routing' => ['prefix' => 'people']]
			],
			'relations' => [
				'related' => ['kind' => 'reference', 'from' => ['post'], 'to' => ['post'], 'symmetric' => true],
				'actors'  => ['kind' => 'reference', 'from' => ['movie'], 'to' => ['person'], 'ordered' => true]
			]
		]);

		$this->entry('_posts/a.md', "title: A\npublished: 2026-01-01\nrelated: [b]");
		$this->entry('_posts/b.md', "title: B\npublished: 2026-01-02");
		$this->entry('_posts/c.md', "title: C\npublished: 2026-01-03\nrelated: [b, d]");
		$this->entry('_posts/d.md', "title: D\npublished: 2026-01-04\nstatus: draft");
		$this->entry('_movies/big.md', "title: Big\npublished: 2026-01-01\nactors: [tom, meg]");
		$this->entry('_movies/splash.md', "title: Splash\npublished: 2026-02-01\nactors: [tom]");
		$this->entry('_people/tom.md', 'title: Tom');
		$this->entry('_people/meg.md', 'title: Meg');

		$this->writeTemporaryFile('resources/views/single-post.php', '<?= e(implode(", ", array_map(fn ($post) => $post->title, $template->related($entry, "related")))) ?>');
		$this->writeTemporaryFile('resources/views/single-movie.php', '<?= e(implode(", ", array_map(fn ($person) => $person->title, $template->related($entry, "actors")))) ?>');
		$this->writeTemporaryFile('resources/views/single-person.php', '<?= e(implode(", ", array_map(fn ($movie) => $movie->title, $template->referencedBy($entry, "movie.actors")))) ?>');
	}

	private function body(string $uri): string
	{
		return (string) $this->site()->container()->make(Kernel::class)->handle(Request::create($uri))->getBody();
	}

	public function testTemplatesListAnEntrysLinksBothWays(): void
	{
		$this->assertSame('Tom, Meg', $this->body('/movies/big'), 'In the order front matter lists them.');
		$this->assertSame('Splash, Big', $this->body('/people/tom'), 'What links to it, newest first (D-596).');
		$this->assertSame('A, C', $this->body('/archives/b'), 'A symmetric relation answers from both ends.');
		$this->assertSame('B', $this->body('/archives/c'), 'Drafts are left out.');
	}
}
