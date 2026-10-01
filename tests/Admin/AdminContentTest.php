<?php

/**
 * Admin content screens' API tests.
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
use Psr\Http\Message\ResponseInterface;
use Blush\Admin\ComponentsController;
use Blush\Admin\EntriesController;
use Blush\Admin\HealthController;
use Blush\Admin\PreviewLinkController;
use Blush\Admin\TypesController;
use Blush\Component\Callout;
use Blush\Component\ComponentContent;
use Blush\Component\ComponentRegistry;
use Blush\Content\ContentRepository;
use Blush\Content\Type\TypeLabels;
use Blush\Field\Fields\TextField;

#[CoversClass(ComponentsController::class)]
#[CoversClass(EntriesController::class)]
#[CoversClass(HealthController::class)]
#[CoversClass(PreviewLinkController::class)]
#[CoversClass(TypesController::class)]
final class AdminContentTest extends TestCase
{
	use BootsAdmin;

	/**
	 * Writes drafts by two authors, a scheduled entry, a published one,
	 * and one with a problem, then boots the admin.
	 *
	 * @param list<string>           $roles
	 * @param array<string, ?string> $environment
	 */
	private function site(array $roles, array $environment = []): void
	{
		// Pages don't credit authors unless the site says so (D-329).
		$this->writeTemporaryFile('user/data/types/page.yaml', "kind: pages\nauthors: true\n");
		$this->writeTemporaryFile('user/content/jane-draft.md', "---\ntitle: Jane's draft\nstatus: draft\nauthors: jane\n---\n");
		$this->writeTemporaryFile('user/content/sam-draft.md', "---\ntitle: Sam's draft\nstatus: draft\nauthors: sam\n---\n");
		$this->writeTemporaryFile('user/content/soon.md', "---\ntitle: Soon\npublished: 2099-01-01 09:00:00\nauthors: jane\n---\n");
		$this->writeTemporaryFile('user/content/live.md', "---\ntitle: Live\nauthors: jane\n---\n");
		$this->writeTemporaryFile('user/content/broken.md', "---\ntitle: Broken\nstatus: pending\nsurprise: yes\n---\n");

		$this->boot(roles: $roles, environment: $environment);
		$this->login();
	}

	/**
	 * Asks for a preview link to an entry, by title.
	 */
	private function preview(string $title, string $token): ResponseInterface
	{
		$entry = array_find([...$this->entries('draft'), ...$this->entries('scheduled')], static fn (array $entry): bool => $entry['title'] === $title);
		$id    = is_array($entry) ? $entry['id'] : 'missing';

		return $this->send('POST', '/previews', json_encode(['entry' => $id]) ?: '', ['X-CSRF-Token' => $token]);
	}

	/**
	 * Returns the signed-in session's CSRF token.
	 */
	private function token(): string
	{
		$token = self::json($this->send('GET', '/session'))['csrfToken'] ?? null;
		$this->assertIsString($token);

		return $token;
	}

	/**
	 * @return list<array<mixed>>
	 */
	private function entries(string $status): array
	{
		$entries = self::json($this->send('GET', "/entries?status={$status}"))['entries'] ?? null;
		$this->assertIsArray($entries);

		/** @var list<array<mixed>> $entries */
		return $entries;
	}

	public function testEditorsSeeEveryDraftAndScheduledEntry(): void
	{
		$this->site(['editor']);

		$drafts = $this->entries('draft');

		$this->assertEqualsCanonicalizing(["Jane's draft", "Sam's draft"], array_column($drafts, 'title'));

		$jane = array_find($drafts, static fn (array $entry): bool => $entry['title'] === "Jane's draft");

		$this->assertIsArray($jane);
		$this->assertTrue($jane['own']);
		$this->assertSame(['jane'], $jane['authors']);
		$this->assertSame('jane-draft.md', $jane['path']);
		$this->assertSame('draft', $jane['status']);

		$scheduled = $this->entries('scheduled');

		$this->assertSame(['Soon'], array_column($scheduled, 'title'));
		$this->assertIsString($scheduled[0]['published'] ?? null);
		$this->assertStringStartsWith('2099-01-01T09:00:00', $scheduled[0]['published']);
	}

	public function testPinsATypesAuthorsPage(): void
	{
		$this->writeTemporaryFile('user/data/types/post.yaml', "folder: _posts\n");
		$this->writeTemporaryFile('user/content/_posts/one.md', "---\ntitle: One\n---\n");
		$this->writeTemporaryFile('user/content/_posts/_authors.md', "---\ntitle: Our Writers\n---\nThe people.\n");
		$this->site(['editor']);

		$list = $this->list('?type=post');

		$this->assertSame(['One'], array_column(is_array($list['entries'] ?? null) ? $list['entries'] : [], 'title'), 'Set apart, like the index page (D-329).');
		$this->assertSame(1, $list['total'] ?? null);
		$page = $list['authorsPage'] ?? null;

		$this->assertIsArray($page);
		$this->assertIsArray($page['can'] ?? null);
		$this->assertSame(['Our Writers', true, false], [$page['title'] ?? null, $page['authorsPage'] ?? null, $page['can']['duplicate'] ?? null]);
		$this->assertNull($this->list('?type=post&page=2')['authorsPage'] ?? null, 'On the first page only.');

		$entry = self::json($this->send('GET', '/content/post/_authors'));

		$this->assertTrue($entry['authorsPage'] ?? null);
		$this->assertIsArray($entry['can'] ?? null);
		$this->assertIsArray($entry['type'] ?? null);
		$this->assertFalse($entry['can']['rename'] ?? null, 'Its slug is what makes it the authors page.');
		$this->assertSame(['title', 'status'], array_column(is_array($entry['type']['fields'] ?? null) ? $entry['type']['fields'] : [], 'name'), 'Without the type\'s fields, like an index page.');
	}

	public function testAuthorsSeeTheirOwn(): void
	{
		$this->site(['author']);

		$this->assertSame(["Jane's draft"], array_column($this->entries('draft'), 'title'));
		$this->assertSame(['Soon'], array_column($this->entries('scheduled'), 'title'));
	}

	public function testContributorsSeeOnlyTheirDrafts(): void
	{
		$this->site(['contributor']);

		$this->assertSame(["Jane's draft"], array_column($this->entries('draft'), 'title'));
		$this->assertSame([], $this->entries('scheduled'), 'A scheduled entry isn\'t a draft, so a contributor can\'t edit it.');
	}

	/**
	 * Returns a page of the entry list.
	 *
	 * @return array<mixed>
	 */
	private function list(string $query = ''): array
	{
		$response = $this->send('GET', "/entries{$query}");

		$this->assertSame(200, $response->getStatusCode());

		return self::json($response);
	}

	/**
	 * Returns a page of the entry list's entries.
	 *
	 * @return list<array<mixed>>
	 */
	private function listed(string $query = ''): array
	{
		$entries = $this->list($query)['entries'] ?? null;
		$this->assertIsArray($entries);

		/** @var list<array<mixed>> $entries */
		return $entries;
	}

	public function testListsEveryEntryTheAccountMayEdit(): void
	{
		$this->site(['editor']);

		$list = $this->list();

		$this->assertSame('any', $list['status']);
		$this->assertSame(5, $list['total']);
		$this->assertSame(1, $list['pages']);
		$this->assertIsArray($list['entries']);
		$this->assertEqualsCanonicalizing(["Jane's draft", "Sam's draft", 'Soon', 'Live', 'Broken'], array_column($list['entries'], 'title'));


		$this->assertEqualsCanonicalizing(['Live', 'Broken'], array_column($this->listed('?status=published'), 'title'));
	}

	public function testListsAnAuthorsOwnEntries(): void
	{
		$this->site(['author']);

		$this->assertEqualsCanonicalizing(["Jane's draft", 'Soon', 'Live'], array_column($this->listed(), 'title'));
	}

	public function testSearchesTitlesAndPaths(): void
	{
		$this->site(['editor']);

		$this->assertSame(["Jane's draft", "Sam's draft"], array_column($this->listed('?search=DRAFT&status=draft'), 'title'), 'In any case.');
		$this->assertSame(['Soon'], array_column($this->listed('?search=soon.md'), 'title'));
		$this->assertSame(0, $this->list('?search=nothing')['total']);
	}

	public function testFiltersByType(): void
	{
		$this->site(['editor']);

		$this->assertSame(5, $this->list('?type=page')['total']);
		$this->assertSame(400, $this->send('GET', '/entries?type=missing')->getStatusCode());
	}

	public function testFiltersByAuthorTermsAndUpdated(): void
	{
		$this->writeTemporaryFile('user/data/types/topic.json', '{"taxonomy": true, "folder": "topics"}');
		$this->writeTemporaryFile('user/content/old.md', "---\ntitle: Old\nauthors: sam\ntopic: [art, books]\nupdated: 2001-01-01\n---\n");
		$this->writeTemporaryFile('user/content/art.md', "---\ntitle: Art\nauthors: jane\ntopic: art\n---\n");
		$this->site(['editor']);

		$this->assertEqualsCanonicalizing(["Sam's draft", 'Old'], array_column($this->listed('?author=sam'), 'title'));
		$this->assertEqualsCanonicalizing(['Old', 'Art'], array_column($this->listed('?terms=topic:art'), 'title'));
		$this->assertSame(['Old'], array_column($this->listed('?terms=topic:art,topic:books'), 'title'), 'An entry needs every term.');
		$this->assertSame(['Art'], array_column($this->listed('?terms=topic:art&author=jane'), 'title'));
		$this->assertNotContains('Old', array_column($this->listed('?days=30'), 'title'));
		$this->assertContains('Old', array_column($this->listed('?days=36500'), 'title'));

		$list = $this->list('?author=sam&terms=topic:art&days=7');

		$this->assertSame(['sam', ['topic:art'], 7, null, null], [$list['author'], $list['terms'], $list['days'], $list['sort'], $list['dir']]);
	}

	public function testSortsByAColumn(): void
	{
		$this->site(['editor']);

		$this->assertSame(['Broken', "Jane's draft", 'Live', "Sam's draft", 'Soon'], array_column($this->listed('?sort=title'), 'title'));
		$this->assertSame(['Soon', "Sam's draft", 'Live', "Jane's draft", 'Broken'], array_column($this->listed('?sort=title&dir=desc'), 'title'));
		$this->assertSame(['draft', 'draft', 'published', 'published', 'scheduled'], array_column($this->listed('?sort=status'), 'status'), 'A scheduled entry sorts as scheduled.');
		$first = $this->listed('?sort=author&dir=desc')[0] ?? [];

		$this->assertSame(['sam'], $first['authors'] ?? null, 'Authors sort by their slug.');

		$list = $this->list('?sort=updated');

		$this->assertSame(['updated', 'desc'], [$list['sort'], $list['dir']], 'Updated sorts newest first.');
	}

	public function testFiltersAndSortsFlattenTrees(): void
	{
		$this->writeTemporaryFile('user/data/types/topic.json', '{"taxonomy": true, "folder": "topics", "hierarchical": true}');
		$this->writeTemporaryFile('user/content/topics/books.md', "---\ntitle: Books\n---\n");
		$this->writeTemporaryFile('user/content/topics/book-reviews.md', "---\ntitle: Book Reviews\nparent: books\nupdated: 2001-01-01\n---\n");
		$this->site(['editor']);

		$this->assertTrue($this->list('?type=topic')['tree']);

		foreach (['?type=topic&sort=title', '?type=topic&days=30', '?type=topic&status=published'] as $query) {
			$this->assertFalse($this->list($query)['tree'], $query);
		}

		$this->assertSame(['Book Reviews', 'Books'], array_column($this->listed('?type=topic&sort=title'), 'title'));
	}

	public function testCountsHowManyPublishedEntriesUseATerm(): void
	{
		$this->writeTemporaryFile('user/content/authors/jane.md', "---\ntitle: Jane\n---\n");
		$this->writeTemporaryFile('user/content/authors/nobody.md', "---\ntitle: Nobody\n---\n");
		$this->site(['editor']);

		$terms = array_column($this->listed('?type=author'), 'uses', 'title');

		$this->assertEqualsCanonicalizing(['Jane' => 1, 'Nobody' => 0], $terms, 'Only "Live" counts: drafts and scheduled entries aren\'t published.');
		$pages = $this->listed('?type=page');

		$this->assertNotSame([], $pages);
		$this->assertSame(array_fill(0, count($pages), null), array_column($pages, 'uses'), 'Only terms have uses.');
	}

	public function testNamesTheEntriesAboveAnEntry(): void
	{
		$this->writeTemporaryFile('user/content/guides/index.md', "---\ntitle: Guides\n---\n");
		$this->writeTemporaryFile('user/content/guides/setup/index.md', "---\ntitle: Setup\n---\n");
		$this->writeTemporaryFile('user/content/guides/setup/install.md', "---\ntitle: Install\n---\n");
		$this->site(['editor']);

		$ancestors = array_column($this->listed('?type=page&search=guides'), 'ancestors', 'title');

		$this->assertSame(['Guides' => [], 'Setup' => ['Guides'], 'Install' => ['Guides', 'Setup']], array_intersect_key($ancestors, ['Guides' => true, 'Setup' => true, 'Install' => true]));
	}

	public function testListsNestingTypesAsATree(): void
	{
		$this->writeTemporaryFile('user/data/types/topic.json', '{"taxonomy": true, "folder": "topics", "hierarchical": true}');
		$this->writeTemporaryFile('user/content/topics/book-reviews.md', "---\ntitle: Book Reviews\nparent: books\n---\n");
		$this->writeTemporaryFile('user/content/topics/zoo.md', "---\ntitle: Zoo\nparent: art\n---\n");
		$this->writeTemporaryFile('user/content/topics/books.md', "---\ntitle: Books\n---\n");
		$this->writeTemporaryFile('user/content/topics/art.md', "---\ntitle: Art\n---\n");
		$this->writeTemporaryFile('user/content/topics/orphan.md', "---\ntitle: Orphan\nparent: gone\n---\n");
		$this->site(['editor']);

		$this->assertSame(['Art', 'Zoo', 'Books', 'Book Reviews', 'Orphan'], array_column($this->listed('?type=topic'), 'title'));
		$this->assertSame([0, 1, 0, 1, 0], array_column($this->listed('?type=topic'), 'depth'), 'An orphan sits at the top.');

		$page = $this->list('?type=topic&per=2&page=2');

		$this->assertIsArray($page['entries'] ?? null);
		$this->assertSame(['Books', 'Book Reviews'], array_column($page['entries'], 'title'));
		$this->assertSame([5, 3], [$page['total'] ?? null, $page['pages'] ?? null]);
		$this->assertSame([false, false], array_column($page['entries'], 'continued'), 'A page that starts at the top needs nothing above it.');

		$page = $this->list('?type=topic&per=3&page=2');

		$this->assertIsArray($page['entries'] ?? null);
		$this->assertSame(['Books', 'Book Reviews', 'Orphan'], array_column($page['entries'], 'title'), 'A page inside a branch starts with its parent.');
		$this->assertSame([true, false, false], array_column($page['entries'], 'continued'));
		$this->assertSame([5, 2], [$page['total'] ?? null, $page['pages'] ?? null], 'Continued entries aren\'t counted again.');
		$this->assertSame([1, 0, 0], array_column($page['entries'], 'children'));
		$this->assertSame(['Book Reviews', 'Books'], array_column($this->listed('?type=topic&search=book'), 'title'), 'A search keeps the usual order.');
		$this->assertSame([null, null], array_column($this->listed('?type=topic&search=book'), 'depth'), 'Only a tree has depths.');
		$this->assertSame([null, null], array_column($this->listed('?type=topic&search=book'), 'children'));
	}

	public function testDescribesTheContentTypes(): void
	{
		$this->writeTemporaryFile('user/data/types/genre.json', '{"taxonomy": true, "types": ["page"], "hierarchical": true, "description": "Kinds of writing.", "icon": "book-open"}');
		$this->site(['author']);

		$types = self::json($this->send('GET', '/types'))['types'] ?? null;

		$this->assertIsArray($types);

		$described = array_map(static fn (mixed $type): array => is_array($type) ? array_intersect_key($type, array_flip(['name', 'labels', 'kind', 'dated', 'types'])) : [], $types);

		$this->assertContains(['name' => 'page', 'labels' => TypeLabels::named('page')->all(), 'kind' => 'pages', 'dated' => false], $described, 'Only taxonomies name types.');
		$this->assertContains(['name' => 'genre', 'labels' => TypeLabels::named('genre')->all(), 'kind' => 'taxonomy', 'dated' => false, 'types' => ['page']], $described);
		$this->assertContains(['name' => 'author', 'labels' => TypeLabels::named('author')->all(), 'kind' => 'authors', 'dated' => false, 'types' => ['page']], $described, 'The authors type names the types that credit authors.');
		$this->assertSame([true, false, false], array_map(static fn (string $name): mixed => array_find($types, static fn (mixed $type): bool => is_array($type) && ($type['name'] ?? null) === $name)['authors'] ?? null, ['page', 'genre', 'author']), 'Whether each type credits authors.');

		$genre = array_find($types, static fn (mixed $type): bool => is_array($type) && ($type['name'] ?? null) === 'genre');

		$this->assertIsArray($genre);
		$this->assertSame(['data', '_genre', '/genre'], [$genre['origin'] ?? null, $genre['folder'] ?? null, $genre['prefix'] ?? null]);
		$this->assertSame(['Kinds of writing.', 'book-open', true], [$genre['description'] ?? null, $genre['icon'] ?? null, $genre['hierarchical'] ?? null]);

		$last = end($types);

		$this->assertIsArray($last);
		$this->assertSame('taxonomy', $last['kind'] ?? null, 'Taxonomies come last.');
		$this->assertSame('author', self::json($this->send('GET', '/types'))['authors'] ?? null);
	}

	public function testDescribesTheComponentsTheInserterOffers(): void
	{
		$this->site(['author']);

		$registry = $this->app->container()->make(ComponentRegistry::class);
		$registry->register('app/note', Callout::class);
		$registry->register('acme/panel', Callout::class);
		$registry->register('acme/tabs', content: ComponentContent::Blocks, props: [new TextField('title')]);

		$components = self::json($this->send('GET', '/components'))['components'] ?? null;

		$this->assertIsArray($components);

		$callout = $this->component($components, 'blush/callout');

		$this->assertSame(
			['label' => 'Callout', 'content' => 'blocks', 'kind' => 'container', 'category' => 'text', 'source' => null],
			array_intersect_key($callout, array_flip(['label', 'content', 'kind', 'category', 'source'])),
			'The inserter writes full names.'
		);
		$this->assertSame('inline', $this->component($components, 'blush/kbd')['kind'] ?? null);
		$this->assertSame('container', $this->component($components, 'blush/figure')['kind'] ?? null);
		$this->assertSame('layout', $this->component($components, 'blush/figure')['category'] ?? null, 'A figure wraps anything, so it\'s layout, not media.');
		$this->assertSame('leaf', $this->component($components, 'blush/embed')['kind'] ?? null);
		$this->assertSame(['image'], $this->component($components, 'blush/gallery')['only'] ?? null, 'A gallery holds images.');
		$this->assertNull($this->component($components, 'blush/callout')['only'] ?? null);

		$this->assertSame(
			[
				['name' => 'info', 'label' => 'Info', 'description' => 'Something useful to know.', 'source' => null],
				['name' => 'tip', 'label' => 'Tip', 'description' => 'A suggestion that helps.', 'source' => null],
				['name' => 'warning', 'label' => 'Warning', 'description' => 'Something to be careful about.', 'source' => null],
				['name' => 'danger', 'label' => 'Danger', 'description' => 'Something that can break things or lose data.', 'source' => null]
			],
			$callout['variants'] ?? null,
			'Variants come with their text; Default isn\'t one.'
		);

		$props = $this->component($components, 'blush/button')['props'] ?? null;
		$this->assertIsArray($props);

		$position = array_find($props, static fn (mixed $prop): bool => is_array($prop) && ($prop['name'] ?? null) === 'iconPosition');
		$this->assertIsArray($position);
		$this->assertSame('Icon position', $position['label'] ?? null);

		$choices = $position['choices'] ?? null;
		$this->assertIsArray($choices);
		$this->assertSame('After the text', $choices['end'] ?? null);

		$note = $this->component($components, 'app/note');

		$this->assertSame(['kind' => 'site', 'label' => 'This site'], $note['source'] ?? null);
		$this->assertArrayHasKey('category', $note);
		$this->assertNull($note['category']);
		$this->assertSame(['kind' => 'extension', 'label' => 'acme'], $this->component($components, 'acme/panel')['source'] ?? null);
		$this->assertNotContains('acme/tabs', array_column($components, 'name'), 'Only components with a class are offered.');

		$image = self::json($this->send('GET', '/components'))['image'] ?? null;

		$this->assertIsArray($image);
		$this->assertIsArray($image['variants'] ?? null);
		$this->assertSame(
			['name' => 'inline-left', 'label' => 'Float Left', 'description' => 'Set left, with the text wrapping beside it.', 'source' => null],
			$image['variants'][0] ?? null,
			'Images have the theme\'s variants, each a class.'
		);

		$this->assertSame(
			['wide' => 'bleed-wide', 'full' => 'bleed-full'],
			self::json($this->send('GET', '/components'))['bleed'] ?? null,
			'Bleed classes default when no theme names them.'
		);
	}

	/**
	 * Returns a component from `GET components`, by name.
	 *
	 * @param  array<mixed> $components
	 * @return array<mixed>
	 */
	private function component(array $components, string $name): array
	{
		$component = array_find($components, static fn (mixed $item): bool => is_array($item) && ($item['name'] ?? null) === $name);
		$this->assertIsArray($component, $name);

		return $component;
	}

	public function testDescribesOneContentType(): void
	{
		$this->writeTemporaryFile('user/data/types/genre.json', '{"taxonomy": true, "types": ["page"], "fields": [{"name": "color", "type": "text"}]}');
		$this->site(['author']);

		$page = self::json($this->send('GET', '/types/page'));

		$this->assertSame('page', $page['name'] ?? null);
		$this->assertContains('genre', is_array($page['taxonomies'] ?? null) ? $page['taxonomies'] : [], 'A taxonomy grouping it.');
		$this->assertNotContains('author', is_array($page['taxonomies'] ?? null) ? $page['taxonomies'] : [], 'The authors type isn\'t a taxonomy (D-329).');
		$this->assertTrue($page['authors'] ?? null);

		$genre = self::json($this->send('GET', '/types/genre'));

		$this->assertTrue($genre['editable'] ?? null, 'Types in user/data/types will be editable.');
		$this->assertSame(['color'], array_column(is_array($genre['fields'] ?? null) ? $genre['fields'] : [], 'name'));
		$this->assertSame(404, $this->send('GET', '/types/missing')->getStatusCode());
	}

	public function testPagesThroughEntries(): void
	{
		$this->site(['editor']);

		$first = $this->list('?per=2');

		$this->assertSame([5, 3, 2], [$first['total'], $first['pages'], $first['per']]);
		$this->assertCount(2, $this->listed('?per=2'));
		$this->assertCount(1, $this->listed('?per=2&page=3'));
		$this->assertSame([], $this->listed('?per=2&page=4'));
	}

	public function testRefusesMalformedLists(): void
	{
		$this->site(['editor']);

		$queries = ['?status=pending', '?page=0', '?page=two', '?per=101', '?search[]=x', '?author[]=x', '?terms=art', '?terms=missing:art', '?days=0', '?days=soon', '?sort=words', '?dir=up'];

		foreach ($queries as $query) {
			$this->assertSame(400, $this->send('GET', "/entries{$query}")->getStatusCode(), $query);
		}
	}

	public function testReportsContentHealth(): void
	{
		// Credited authors without entries are warnings (D-329).
		$this->writeTemporaryFile('user/content/authors/jane.md', "---\ntitle: Jane\n---\n");
		$this->writeTemporaryFile('user/content/authors/sam.md', "---\ntitle: Sam\n---\n");
		$this->site(['editor']);

		$health = self::json($this->send('GET', '/health'));

		$this->assertIsArray($health['counts'] ?? null);
		$this->assertGreaterThanOrEqual(1, $health['counts']['error'] ?? 0);
		$this->assertNull($health['counts']['notice'] ?? null);
		$this->assertIsArray($health['files'] ?? null);
		$this->assertSame(['broken.md'], array_column($health['files'], 'path'));

		$strict = self::json($this->send('GET', '/health?strict=1'));

		$this->assertTrue($strict['strict'] ?? null);
		$this->assertIsArray($strict['counts'] ?? null);
		$this->assertIsInt($strict['counts']['notice'] ?? null);
	}

	public function testOnlyEditorsSeeContentHealth(): void
	{
		$this->site(['author']);

		$this->assertSame(403, $this->send('GET', '/health')->getStatusCode());
	}

	public function testMakesPreviewLinksForEntriesTheAccountMayEdit(): void
	{
		$this->site(['author']);
		$token = $this->token();

		$response = $this->preview("Jane's draft", $token);
		$link     = self::json($response);

		$this->assertSame(201, $response->getStatusCode());
		$this->assertIsString($link['url'] ?? null);
		$this->assertStringStartsWith('https://example.test/_blush/preview?entry=', $link['url']);
		$this->assertIsString($link['expires'] ?? null);

		$this->assertSame(200, $this->visit('GET', substr($link['url'], strlen('https://example.test')))->getStatusCode());

		$this->assertSame(404, $this->preview("Sam's draft", $token)->getStatusCode(), 'An author can\'t find, so can\'t preview, someone else\'s draft.');
		$this->assertSame(400, $this->send('POST', '/previews', '{}', ['X-CSRF-Token' => $token])->getStatusCode());
	}

	public function testRefusesPreviewsOfOthersEntries(): void
	{
		$this->site(['author']);
		$token = $this->token();

		$sam = $this->app->container()->make(ContentRepository::class)->named('page', 'sam-draft');
		$this->assertNotNull($sam);

		$this->assertSame(403, $this->send('POST', '/previews', json_encode(['entry' => $sam->id]) ?: '', ['X-CSRF-Token' => $token])->getStatusCode());
	}

	public function testSaysWhenPreviewLinksAreOff(): void
	{
		$this->site(['editor'], ['APP_SECRET' => null]);

		$this->assertSame(503, $this->preview("Jane's draft", $this->token())->getStatusCode());
	}
}
