<?php

/**
 * Admin references API tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Admin\ReferencesController;

#[CoversClass(ReferencesController::class)]
final class AdminReferencesTest extends TestCase
{
	use BootsAdmin;

	/**
	 * @param list<string> $roles
	 */
	private function site(array $roles = ['editor']): void
	{
		$this->writeTemporaryFile('user/data/types/topic.json', '{"taxonomy": true, "folder": "topics", "hierarchical": true, "types": ["page"]}');
		$this->writeTemporaryFile('user/data/types/mood.json', '{"taxonomy": true, "folder": "moods", "types": ["page"]}');
		$this->writeTemporaryFile('user/content/topics/index.md', "---\ntitle: Topics\n---\n");
		$this->writeTemporaryFile('user/content/topics/web.md', "---\ntitle: Web\n---\n");
		$this->writeTemporaryFile('user/content/topics/css.md', "---\ntitle: CSS\nparent: web\n---\n");
		$this->writeTemporaryFile('user/content/topics/art.md', "---\ntitle: Art\n---\n");
		$this->writeTemporaryFile('user/content/moods/happy.md', "---\ntitle: Happy\n---\n");
		$this->writeTemporaryFile('user/content/one.md', "---\ntitle: One\ntopic: [css]\nmood: [happy, Book Reviews]\n---\n");
		$this->writeTemporaryFile('user/content/two.md', "---\ntitle: Two\ntopic: css\n---\n");

		$this->boot(roles: $roles);
		$this->login();
	}

	/**
	 * @return array<mixed>
	 */
	private function references(string $path): array
	{
		return self::json($this->send('GET', "/references/{$path}"));
	}

	public function testAHierarchicalTaxonomyIsATree(): void
	{
		$this->site();

		$list  = $this->references('topic');
		$items = is_array($list['items'] ?? null) ? $list['items'] : [];

		$this->assertSame([true, true, 3], [$list['create'] ?? null, $list['tree'] ?? null, $list['total'] ?? null]);
		$this->assertSame(['art', 'web', 'css'], array_column($items, 'slug'), 'Tree order, siblings by title, and no landing page.');
		$this->assertSame([0, 0, 1], array_column($items, 'depth'));
		$this->assertSame([null, null, 'web'], array_column($items, 'parent'));
		$this->assertSame([0, 0, 2], array_column($items, 'uses'));
	}

	public function testATreesPagesInTreeOrderWhenAsked(): void
	{
		$this->writeTemporaryFile('user/content/two/alpha.md', "---\ntitle: Alpha\n---\n");
		$this->site();

		$list  = $this->references('page?tree=1');
		$items = is_array($list['items'] ?? null) ? $list['items'] : [];

		$this->assertTrue($list['tree'] ?? null);
		$this->assertSame(['one', 'two', 'two/alpha'], array_column($items, 'slug'), 'A parent\'s pages under it (D-408).');
		$this->assertSame([0, 0, 1], array_column($items, 'depth'));
		$this->assertFalse($this->references('page')['tree'] ?? null, 'Unasked, a search as before.');
	}

	public function testAFlatTaxonomyHasItsTermsAndSearches(): void
	{
		$this->site();

		$items = (array) ($this->references('mood')['items'] ?? []);

		$this->assertSame(['happy'], array_column($items, 'slug'), 'Book Reviews has no file, so it isn\'t a term (D-584).');
		$this->assertTrue($this->references('mood')['create'] ?? null, 'New ones are written as they\'re typed.');
		$this->assertSame(['happy'], array_column((array) ($this->references('mood?search=HAP')['items'] ?? []), 'slug'));
	}

	public function testAnswersTheFieldsOwnSlugs(): void
	{
		$this->site();

		$items = (array) ($this->references('mood?search=zzz&slugs=happy,gone')['items'] ?? []);

		$this->assertSame(['happy', 'gone'], array_column($items, 'slug'), 'Held slugs come back, found or not.');
		$this->assertSame([false, true], array_column($items, 'missing'));
		$this->assertSame(1, count((array) ($this->references('page?limit=1')['items'] ?? [])));
	}

	public function testForATypeOnlyItsTermsInUse(): void
	{
		$this->writeTemporaryFile('user/content/draft.md', "---\ntitle: Draft\nstatus: draft\nmood: gloomy\n---\n");
		$this->site();

		$topics = (array) ($this->references('topic?for=page')['items'] ?? []);
		$moods  = (array) ($this->references('mood?for=page')['items'] ?? []);

		$this->assertSame(['web', 'css'], array_column($topics, 'slug'), 'Art is unused; Web stays as CSS\'s parent.');
		$this->assertSame(['happy'], array_column($moods, 'slug'), 'Slugs with no file aren\'t terms (D-584).');
		$this->assertSame([], (array) ($this->references('topic?for=profile')['items'] ?? ['x']), 'No profile uses a topic.');
	}

	public function testChecksItsInput(): void
	{
		$this->site();

		$this->assertSame(404, $this->send('GET', '/references/nope')->getStatusCode());

		foreach (['?limit=0', '?limit=101', '?search[]=x', '?for=nope', '?for[]=page'] as $query) {
			$this->assertSame(400, $this->send('GET', "/references/mood{$query}")->getStatusCode(), $query);
		}
	}

	public function testAnAuthorSeesWhatTheyCantEdit(): void
	{
		$this->site(['author']);

		$this->assertSame(3, $this->references('topic')['total'] ?? null);
	}

	public function testUptoAnswersEveryCandidateOrNone(): void
	{
		$this->site();

		$whole = $this->references('topic?upto=3');
		$over  = $this->references('topic?upto=2&slugs=css');

		$this->assertSame([true, true, 3], [$whole['whole'] ?? null, $whole['tree'] ?? null, $whole['total'] ?? null]);
		$this->assertSame(['art', 'web', 'css'], array_column((array) ($whole['items'] ?? []), 'slug'));
		$this->assertSame([false, false, 3], [$over['whole'] ?? null, $over['tree'] ?? null, $over['total'] ?? null], 'Over it, the count and no tree (D-607).');
		$this->assertSame(['css'], array_column((array) ($over['items'] ?? []), 'slug'), 'Only what the field holds.');
		$this->assertSame(['Web'], array_column((array) ($over['items'] ?? []), 'path'));
	}

	public function testASearchRanksTitlesStartingWithItFirst(): void
	{
		$this->writeTemporaryFile('user/content/moods/sea-salt.md', "---\ntitle: Sea Salt\n---\n");
		$this->writeTemporaryFile('user/content/moods/wasabi.md', "---\ntitle: Wasabi\n---\n");
		$this->writeTemporaryFile('user/content/moods/saffron.md', "---\ntitle: Saffron\n---\n");
		$this->site();

		$list = $this->references('mood?upto=2&search=sa&limit=2');

		$this->assertSame(['saffron', 'sea-salt'], array_column((array) ($list['items'] ?? []), 'slug'), 'Starts with, then a word, then anywhere.');
		$this->assertSame([3, false], [$list['total'] ?? null, $list['whole'] ?? null]);
	}

	public function testSuggestsTheMostUsedTheEditedAndTheRecent(): void
	{
		$this->site();

		$this->assertSame(['css'], array_column((array) ($this->references('topic?suggest=uses')['suggested'] ?? []), 'slug'), 'Only terms in use, most first.');
		$this->assertSame(['css'], array_column((array) ($this->references('topic?suggest=recent&from=page.topic')['suggested'] ?? []), 'slug'));
		$this->assertCount(2, (array) ($this->references('topic?suggest=edited&suggestions=2')['suggested'] ?? []));
		$this->assertSame(400, $this->send('GET', '/references/topic?suggest=recent')->getStatusCode(), 'Recent needs a relation.');
		$this->assertSame(400, $this->send('GET', '/references/topic?suggest=nope')->getStatusCode());
	}

	public function testLeavesOutAnEntryAndItsBranch(): void
	{
		$this->site();

		$alone  = $this->references('topic?except=web');
		$branch = $this->references('topic?except=web&branch=1');

		$this->assertSame(['art', 'css'], array_column((array) ($alone['items'] ?? []), 'slug'));
		$this->assertSame(['art'], array_column((array) ($branch['items'] ?? []), 'slug'));
		$this->assertSame(1, $branch['excluded'] ?? null, 'CSS, under Web.');
	}

	public function testATrashedOrMistypedSlugSaysSo(): void
	{
		$this->writeTemporaryFile('user/content/moods/gloomy.md', "---\ntitle: Gloomy\nstatus: trash\n---\n");
		$this->site();

		$items = array_slice((array) ($this->references('mood?upto=1&slugs=gloomy,hapy,zzzzzzzz')['items'] ?? []), 1);

		$this->assertSame(['trash', null, null], array_column($items, 'status'));
		$this->assertSame([false, true, true], array_column($items, 'missing'));
		$this->assertSame([['slug' => 'happy', 'title' => 'Happy'], null], array_column(array_slice($items, 1), 'closest'), 'A typo finds what it meant.');
	}
}
