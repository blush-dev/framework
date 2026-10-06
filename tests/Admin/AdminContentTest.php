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
use Blush\Admin\DirectivesController;
use Blush\Admin\EntriesController;
use Blush\Admin\HealthController;
use Blush\Admin\PreviewLinkController;
use Blush\Admin\TypesController;
use Blush\Directive\Callout;
use Blush\Directive\DirectiveRegistry;
use Blush\Content\ContentRepository;
use Blush\Content\Type\TypeLabels;

#[CoversClass(DirectivesController::class)]
#[CoversClass(EntriesController::class)]
#[CoversClass(HealthController::class)]
#[CoversClass(PreviewLinkController::class)]
#[CoversClass(TypesController::class)]
final class AdminContentTest extends TestCase
{
	use BootsAdmin;

	/**
	 * The published entry's id.
	 */
	private const string LIVE = '0199b6e2-0000-7000-8000-000000000004';

	/**
	 * Writes drafts by two authors, a scheduled entry, a published one,
	 * and one with a problem, then boots the admin.
	 *
	 * @param list<string>           $roles
	 * @param array<string, ?string> $environment
	 */
	private function site(array $roles, array $environment = [], bool $ids = true): void
	{
		// Pages don't credit authors unless the site says so (D-329).
		$this->writeTemporaryFile('user/data/types/page.yaml', "kind: tree\nauthors: true\n");
		$this->writeTemporaryFile('user/content/jane-draft.md', "---\ntitle: Jane's draft\nstatus: draft\nauthors: jane\nid: 0199b6e2-0000-7000-8000-000000000001\n---\n");
		$this->writeTemporaryFile('user/content/sam-draft.md', "---\ntitle: Sam's draft\nstatus: draft\nauthors: sam\nid: 0199b6e2-0000-7000-8000-000000000002\n---\n");
		$this->writeTemporaryFile('user/content/soon.md', "---\ntitle: Soon\npublished: 2099-01-01 09:00:00\nauthors: jane\nid: 0199b6e2-0000-7000-8000-000000000003\n---\n");
		$this->writeTemporaryFile('user/content/live.md', "---\ntitle: Live\nauthors: jane\nid: " . self::LIVE . "\n---\n");
		$this->writeTemporaryFile('user/content/broken.md', "---\ntitle: Broken\nstatus: pending\nsurprise: yes\nid: 0199b6e2-0000-7000-8000-000000000005\n---\n");

		$this->boot(roles: $roles, environment: $environment, ids: $ids);
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

		$entry = self::json($this->send('GET', $this->entryPath('_posts/_authors.md')));

		$this->assertTrue($entry['authorsPage'] ?? null);
		$this->assertIsArray($entry['can'] ?? null);
		$this->assertIsArray($entry['type'] ?? null);
		$this->assertFalse($entry['can']['rename'] ?? null, 'Its slug is what makes it the authors page.');
		$this->assertSame(['title', 'status'], array_column(is_array($entry['type']['fields'] ?? null) ? $entry['type']['fields'] : [], 'name'), 'Without the type\'s fields, like an index page.');
	}

	public function testPinsTheErrorPagesOnPages(): void
	{
		$this->writeTemporaryFile('user/content/_errors/500.md', "---\ntitle: Something Broke\n---\n");
		$this->writeTemporaryFile('user/content/_error/404.md', "---\ntitle: Not Here\n---\n");
		$this->writeTemporaryFile('user/content/_errors/notes.md', "---\ntitle: Notes\n---\n");
		$this->site(['editor']);

		$list   = $this->list('?type=page');
		$pinned = is_array($list['errorPages'] ?? null) ? $list['errorPages'] : [];
		$titles = array_column(is_array($list['entries'] ?? null) ? $list['entries'] : [], 'title');

		$this->assertSame(['Not Here', 'Something Broke', 'Notes'], array_column($pinned, 'title'), 'By status, in either folder, then the rest kept there (D-411).');
		$this->assertSame([404, 500, null], array_column($pinned, 'errorPage'));
		$this->assertNotContains('Not Here', $titles, 'Not among the pages.');
		$this->assertSame([], $this->list('?type=page&page=2')['errorPages'] ?? null, 'On the first page only.');

		$entry = self::json($this->send('GET', $this->entryPath('_errors/500.md')));
		$this->assertIsArray($entry['can'] ?? null);
		$this->assertSame([500, false, false, false], [$entry['errorPage'] ?? null, $entry['can']['rename'] ?? null, $entry['can']['move'] ?? null, $entry['can']['duplicate'] ?? null]);

		$parents = self::json($this->send('GET', '/references/page?tree=1'));
		$this->assertNotContains('_errors/500', array_column(is_array($parents['items'] ?? null) ? $parents['items'] : [], 'slug'), 'Nothing goes under an error page.');
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

		$this->assertEqualsCanonicalizing(["Jane's draft", "Sam's draft"], array_column($this->listed('?search=DRAFT&status=draft'), 'title'), 'In any case.');
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
		$this->writeTemporaryFile('user/content/profiles/jane.md', "---\ntitle: Jane\n---\n");
		$this->writeTemporaryFile('user/content/profiles/nobody.md', "---\ntitle: Nobody\n---\n");
		$this->site(['editor']);

		$terms = array_column($this->listed('?type=profile'), 'uses', 'title');

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

		$this->assertSame(['Guides' => [], 'Install' => ['Guides', 'Setup'], 'Setup' => ['Guides']], array_intersect_key($ancestors, ['Guides' => true, 'Setup' => true, 'Install' => true]), 'By title, with no positions (D-413).');
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

	public function testOrdersATreesPagesByPosition(): void
	{
		$this->writeTemporaryFile('user/data/types/topic.json', '{"taxonomy": true, "folder": "topics", "hierarchical": true}');
		$this->writeTemporaryFile('user/content/topics/zebras.md', "---\ntitle: Zebras\nposition: 1\n---\n");
		$this->writeTemporaryFile('user/content/topics/apes.md', "---\ntitle: Apes\nposition: 2\n---\n");
		$this->writeTemporaryFile('user/content/guide/index.md', "---\ntitle: Guide\n---\n");
		$this->writeTemporaryFile('user/content/guide/upgrade.md', "---\ntitle: Upgrade\nposition: 2\n---\n");
		$this->writeTemporaryFile('user/content/guide/install.md', "---\ntitle: Install\nposition: 1\n---\n");
		$this->writeTemporaryFile('user/content/guide/about.md', "---\ntitle: About\n---\n");
		$this->site(['editor']);

		$titles = array_column($this->listed('?type=page&per=100'), 'title');
		$guide  = array_search('Guide', $titles, true);

		$this->assertIsInt($guide);
		$this->assertSame(['Install', 'Upgrade', 'About'], array_slice($titles, $guide + 1, 3), 'A tree\'s pages by position, then title (D-412).');
		$this->assertSame(['Zebras', 'Apes'], array_column($this->listed('?type=topic'), 'title'), 'A taxonomy\'s All tab goes by position too (D-413).');
		$this->assertSame(['Apes', 'Zebras'], array_column($this->listed('?type=topic&sort=title'), 'title'), 'Unless sorted by a column.');

		$parents = self::json($this->send('GET', '/references/page?tree=1'));
		$slugs   = array_column(is_array($parents['items'] ?? null) ? $parents['items'] : [], 'slug');
		$at      = array_search('guide', $slugs, true);

		$this->assertIsInt($at);
		$this->assertSame(['guide/install', 'guide/upgrade', 'guide/about'], array_slice($slugs, $at + 1, 3), 'And in the Parent list.');

		$terms = self::json($this->send('GET', '/references/topic'));
		$this->assertSame(['apes', 'zebras'], array_column(is_array($terms['items'] ?? null) ? $terms['items'] : [], 'slug'), 'Terms stay alphabetical there too.');
	}

	public function testOrdersEachKindsAllTab(): void
	{
		$this->writeTemporaryFile('user/data/types/post.yaml', "folder: _posts\n");
		$this->writeTemporaryFile('user/data/types/mood.json', '{"taxonomy": true, "folder": "moods"}');
		$this->writeTemporaryFile('user/content/_posts/old.md', "---\ntitle: Old\npublished: 2001-01-01\n---\n");
		$this->writeTemporaryFile('user/content/_posts/new.md', "---\ntitle: New\npublished: 2020-01-01\n---\n");
		$this->writeTemporaryFile('user/content/moods/sad.md', "---\ntitle: Sad\n---\n");
		$this->writeTemporaryFile('user/content/moods/glad.md', "---\ntitle: Glad\n---\n");
		$this->writeTemporaryFile('user/content/moods/mad.md', "---\ntitle: Mad\nposition: 1\n---\n");
		$this->writeTemporaryFile('user/content/profiles/zoe.md', "---\ntitle: Zoe\n---\n");
		$this->writeTemporaryFile('user/content/profiles/abe.md', "---\ntitle: Abe\n---\n");
		$this->site(['editor']);

		$posts = $this->list('?type=post');

		$this->assertSame(['New', 'Old'], array_column(is_array($posts['entries'] ?? null) ? $posts['entries'] : [], 'title'), 'A collection, newest published first (D-413).');
		$this->assertSame('published', $posts['by'] ?? null);
		$this->assertSame(['Mad', 'Glad', 'Sad'], array_column($this->listed('?type=mood'), 'title'), 'Terms by position, then title.');
		$this->assertSame('position', $this->list('?type=mood')['by'] ?? null);
		$this->assertSame(['Abe', 'Zoe'], array_slice(array_column($this->listed('?type=profile'), 'title'), 0, 2), 'Profiles by title.');
		$this->assertSame('updated', $this->list('?type=post&status=draft')['by'] ?? null, 'Drafts, most recently changed first.');
	}

	public function testDescribesTheContentTypes(): void
	{
		$this->writeTemporaryFile('user/data/types/genre.json', '{"taxonomy": true, "types": ["page"], "hierarchical": true, "description": "Kinds of writing.", "icon": "book-open"}');
		$this->site(['author']);

		$types = self::json($this->send('GET', '/types'))['types'] ?? null;

		$this->assertIsArray($types);

		$described = array_map(static fn (mixed $type): array => is_array($type) ? array_intersect_key($type, array_flip(['name', 'labels', 'kind', 'dated', 'types'])) : [], $types);

		$this->assertContains(['name' => 'page', 'labels' => TypeLabels::named('page')->all(), 'kind' => 'tree', 'dated' => false], $described, 'Only taxonomies name types.');
		$this->assertContains(['name' => 'genre', 'labels' => TypeLabels::named('genre')->all(), 'kind' => 'taxonomy', 'dated' => false, 'types' => ['page']], $described);
		$this->assertContains(['name' => 'profile', 'labels' => TypeLabels::named('profile')->all(), 'kind' => 'profiles', 'dated' => false, 'types' => ['page']], $described, 'The profiles type names the types that credit people.');
		$this->assertSame([true, false, false], array_map(static fn (string $name): mixed => array_find($types, static fn (mixed $type): bool => is_array($type) && ($type['name'] ?? null) === $name)['authors'] ?? null, ['page', 'genre', 'profile']), 'Whether each type credits people.');

		$genre = array_find($types, static fn (mixed $type): bool => is_array($type) && ($type['name'] ?? null) === 'genre');

		$this->assertIsArray($genre);
		$this->assertSame(['data', '_genre', '/genre'], [$genre['origin'] ?? null, $genre['folder'] ?? null, $genre['prefix'] ?? null]);
		$this->assertSame(['Kinds of writing.', 'book-open', true], [$genre['description'] ?? null, $genre['icon'] ?? null, $genre['hierarchical'] ?? null]);

		$kinds = array_map(static fn (mixed $type): mixed => is_array($type) ? $type['kind'] ?? null : null, $types);

		$this->assertSame(['taxonomy', 'profiles'], array_slice($kinds, -2), 'Term types come last, by name.');
		$this->assertSame('profile', self::json($this->send('GET', '/types'))['authors'] ?? null);
	}

	public function testDescribesTheDirectivesTheInserterOffers(): void
	{
		$this->site(['author']);

		$registry = $this->app->container()->make(DirectiveRegistry::class);
		$registry->register('app/note', Callout::class);
		$registry->register('acme/panel', Callout::class);

		$directives = self::json($this->send('GET', '/directives'))['directives'] ?? null;

		$this->assertIsArray($directives);

		$callout = $this->directive($directives, 'blush/callout');

		$this->assertSame(
			['label' => 'Callout', 'content' => 'blocks', 'kind' => 'container', 'category' => 'text', 'source' => null],
			array_intersect_key($callout, array_flip(['label', 'content', 'kind', 'category', 'source'])),
			'The inserter writes full names.'
		);
		$this->assertSame('inline', $this->directive($directives, 'blush/kbd')['kind'] ?? null);
		$this->assertSame('container', $this->directive($directives, 'blush/figure')['kind'] ?? null);
		$this->assertSame('layout', $this->directive($directives, 'blush/figure')['category'] ?? null, 'A figure wraps anything, so it\'s layout, not media.');
		$this->assertSame('leaf', $this->directive($directives, 'blush/embed')['kind'] ?? null);
		$this->assertSame(['image'], $this->directive($directives, 'blush/gallery')['only'] ?? null, 'A gallery holds images.');
		$this->assertNull($this->directive($directives, 'blush/callout')['only'] ?? null);

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

		$props = $this->directive($directives, 'blush/button')['props'] ?? null;
		$this->assertIsArray($props);

		$position = array_find($props, static fn (mixed $prop): bool => is_array($prop) && ($prop['name'] ?? null) === 'iconPosition');
		$this->assertIsArray($position);
		$this->assertSame('Icon position', $position['label'] ?? null);

		$choices = $position['choices'] ?? null;
		$this->assertIsArray($choices);
		$this->assertSame('After the text', $choices['end'] ?? null);

		$note = $this->directive($directives, 'app/note');

		$this->assertSame(['kind' => 'site', 'label' => 'This site'], $note['source'] ?? null);
		$this->assertArrayHasKey('category', $note);
		$this->assertNull($note['category']);
		$this->assertSame(['kind' => 'plugin', 'label' => 'acme'], $this->directive($directives, 'acme/panel')['source'] ?? null);

		$image = self::json($this->send('GET', '/directives'))['image'] ?? null;

		$this->assertIsArray($image);
		$this->assertIsArray($image['variants'] ?? null);
		$this->assertSame(
			['name' => 'inline-left', 'label' => 'Float Left', 'description' => 'Set left, with the text wrapping beside it.', 'source' => null],
			$image['variants'][0] ?? null,
			'Images have the theme\'s variants, each a class.'
		);

		$this->assertSame(
			['wide' => 'bleed-wide', 'full' => 'bleed-full'],
			self::json($this->send('GET', '/directives'))['bleed'] ?? null,
			'Bleed classes default when no theme names them.'
		);
	}

	/**
	 * Returns a directive from `GET directives`, by name.
	 *
	 * @param  array<mixed> $directives
	 * @return array<mixed>
	 */
	private function directive(array $directives, string $name): array
	{
		$directive = array_find($directives, static fn (mixed $item): bool => is_array($item) && ($item['name'] ?? null) === $name);
		$this->assertIsArray($directive, $name);

		return $directive;
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
		$this->writeTemporaryFile('user/content/profiles/jane.md', "---\ntitle: Jane\nid: 0199b6e2-0000-7000-8000-000000000006\n---\n");
		$this->writeTemporaryFile('user/content/profiles/sam.md', "---\ntitle: Sam\nid: 0199b6e2-0000-7000-8000-000000000007\n---\n");
		$this->site(['owner']);

		$health = self::json($this->send('GET', '/health'));

		// Notices included; the admin hides them until asked (D-546).
		$this->assertIsArray($health['counts'] ?? null);
		$this->assertGreaterThanOrEqual(1, $health['counts']['error'] ?? 0);
		$this->assertIsInt($health['counts']['notice'] ?? null);
		$this->assertTrue($health['strict'] ?? null);
		$this->assertIsString($health['at'] ?? null);
		$this->assertIsArray($health['files'] ?? null);
		$this->assertContains('broken.md', array_column($health['files'], 'path'));
		$this->assertSame(['missing' => [], 'duplicates' => []], $health['ids'] ?? null);
	}

	public function testSumsUpSiteHealth(): void
	{
		$this->site(['owner']);

		$health = self::json($this->send('GET', '/health/site'));

		$this->assertIsArray($health['areas'] ?? null);
		$this->assertSame(['content', 'media', 'extensions', 'system', 'accounts'], array_column($health['areas'], 'key'));
		$this->assertIsArray($health['checks'] ?? null);

		$checks = array_column($health['checks'], null, 'label');
		$this->assertIsArray($checks['Content files'] ?? null);
		$this->assertSame('failure', $checks['Content files']['status'] ?? null, 'broken.md has errors.');
		$this->assertSame('content', $checks['Content files']['link'] ?? null);
		$this->assertIsArray($checks['PHP'] ?? null);
		$this->assertSame(['system', 'pass', null], [$checks['PHP']['area'] ?? null, $checks['PHP']['status'] ?? null, $checks['PHP']['link'] ?? null]);

		$this->assertIsArray($health['requirements'] ?? null);
		$requirements = array_column($health['requirements'], null, 'name');
		$this->assertIsArray($requirements['PHP'] ?? null);
		$this->assertSame('pass', $requirements['PHP']['status'] ?? null);
		$this->assertIsArray($health['site'] ?? null);
		$this->assertContains('Blush', array_column($health['site'], 'label'));

		// The report is kept, and shown again until checked again (D-545).
		$this->assertFileExists($this->temporaryDirectory() . '/storage/health.json');
		$this->writeTemporaryFile('user/content/broken.md', "---\ntitle: Fixed\nid: 0199b6e2-0000-7000-8000-000000000005\n---\n");
		$this->assertSame($health, self::json($this->send('GET', '/health/site')));
		$issues = count(array_filter(array_column($health['checks'], 'status'), static fn (mixed $status): bool => $status !== 'pass'));
		$this->assertSame($issues, self::json($this->send('GET', '/counts'))['health'] ?? null, 'By the last report.');

		// The files' details are kept too, until checked again, which
		// updates the summary (D-546).
		$this->assertIsArray($health['files'] ?? null);
		$this->assertSame($health['files']['at'] ?? null, self::json($this->send('GET', '/health'))['at'] ?? null);
		$this->send('POST', '/health', headers: ['X-CSRF-Token' => $this->token()]);
		$checks = self::json($this->send('GET', '/health/site'))['checks'] ?? null;
		$this->assertIsArray($checks);
		$files = array_column($checks, null, 'label')['Content files'] ?? null;
		$this->assertIsArray($files);
		$this->assertSame('warning', $files['status'] ?? null, 'No errors left; a credited author has no profile.');

		$this->assertSame(403, $this->send('POST', '/health/site')->getStatusCode(), 'Checking again needs the CSRF token.');
		$again = self::json($this->send('POST', '/health/site', headers: ['X-CSRF-Token' => $this->token()]));
		$this->assertIsArray($again['checks'] ?? null);
	}

	public function testOnlySiteHealthSeesContentHealth(): void
	{
		// Editors edit anyone's entries, and administrators run the site,
		// but Site Health is the owner's (D-543, D-544).
		$this->site(['administrator']);
		$token = $this->token();

		$this->assertSame(403, $this->send('GET', '/health')->getStatusCode());
		$this->assertSame(403, $this->send('GET', '/health/site')->getStatusCode());
		$this->assertSame(403, $this->send('POST', '/health/ids', '{}', ['X-CSRF-Token' => $token])->getStatusCode(), 'Nor fix ids.');
		$this->assertSame(403, $this->send('POST', '/health/ids/keep', '{"path": "live.md"}', ['X-CSRF-Token' => $token])->getStatusCode());
		$this->assertSame(403, $this->send('POST', '/health/media-ids', '{}', ['X-CSRF-Token' => $token])->getStatusCode(), 'Nor media ids.');
		$this->assertSame(403, $this->send('POST', '/health/media-ids/keep', '{"path": "a.png"}', ['X-CSRF-Token' => $token])->getStatusCode());
		$this->assertSame(403, $this->send('POST', '/health/media-sizes', '{}', ['X-CSRF-Token' => $token])->getStatusCode(), 'Nor record sizes.');
		$this->assertSame(403, $this->send('POST', '/health/filenames', '{"type": "post"}', ['X-CSRF-Token' => $token])->getStatusCode(), 'Nor rename files.');
		$this->assertSame(403, $this->send('POST', '/health/flatten', '{}', ['X-CSRF-Token' => $token])->getStatusCode(), 'Nor move them.');
	}

	public function testFixesMissingAndSharedIds(): void
	{
		$this->writeTemporaryFile('user/content/no-id.md', "---\ntitle: No ID\n---\n");
		$this->writeTemporaryFile('user/content/bad-id.md', "---\ntitle: Bad ID\nid: 42\n---\n");
		$this->writeTemporaryFile('user/content/copy.md', "---\ntitle: Copy\nid: " . self::LIVE . "\n---\n");
		$this->site(['owner'], ids: false);
		$token = $this->token();

		$health = self::json($this->send('POST', '/health', headers: ['X-CSRF-Token' => $token]));
		$ids    = $health['ids'] ?? null;
		$files  = $health['files'] ?? null;

		$this->assertIsArray($ids);
		$this->assertIsArray($files);
		$this->assertSame(['bad-id.md', 'no-id.md'], $ids['missing'] ?? null, 'Missing ids, and ids that aren\'t UUIDs (D-477).');
		$this->assertSame([['id' => self::LIVE, 'paths' => ['copy.md', 'live.md']]], $ids['duplicates'] ?? null);
		$this->assertContains('no-id.md', array_column($files, 'path'), 'They\'re errors too.');

		$row = array_find($this->entries('any'), static fn (array $entry): bool => $entry['path'] === 'no-id.md');

		$this->assertIsArray($row);
		$this->assertArrayHasKey('id', $row);
		$this->assertNull($row['id'], 'Lists show it, with no id.');

		$assigned = self::json($this->send('POST', '/health/ids', '{}', ['X-CSRF-Token' => $token]))['assigned'] ?? null;

		$this->assertIsArray($assigned);
		$this->assertSame(['bad-id.md', 'no-id.md'], array_keys($assigned));

		$this->assertSame(422, $this->send('POST', '/health/ids/keep', '{"path": "soon.md"}', ['X-CSRF-Token' => $token])->getStatusCode(), 'Only a file that shares its id.');
		$this->assertSame(400, $this->send('POST', '/health/ids/keep', '{}', ['X-CSRF-Token' => $token])->getStatusCode());

		$kept = self::json($this->send('POST', '/health/ids/keep', '{"path": "live.md"}', ['X-CSRF-Token' => $token]));

		$this->assertIsArray($kept['assigned'] ?? null);
		$this->assertSame(['copy.md'], array_keys($kept['assigned']), 'The other file gets a new id.');
		$this->assertSame([], $kept['failed'] ?? null);

		$content = $this->app->container()->make(ContentRepository::class);

		$this->assertSame('live.md', $content->find(self::LIVE)?->path, 'The one kept keeps it.');
		$this->assertSame(['missing' => [], 'duplicates' => []], self::json($this->send('POST', '/health', headers: ['X-CSRF-Token' => $token]))['ids'] ?? null);
	}

	public function testRenamesFilesToTheirTypesPattern(): void
	{
		$this->writeTemporaryFile('user/data/types/post.yaml', "folder: _posts\nfilename: \"{date}.{slug}\"\n");
		$this->writeTemporaryFile('user/content/_posts/one.md', "---\ntitle: One\npublished: 2026-01-02 10:00:00\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e01\n---\n");
		$this->writeTemporaryFile('user/content/_posts/two.md', "---\ntitle: Two\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e02\n---\n");
		touch($this->temporaryDirectory() . '/user/content/_posts/two.md', (int) strtotime('2025-03-04 12:00:00 UTC'));
		$this->site(['owner']);
		$token = $this->token();

		$names = self::json($this->send('POST', '/health', headers: ['X-CSRF-Token' => $token]))['fileNames'] ?? null;

		$this->assertSame([[
			'type'     => 'post',
			'label'    => 'Posts',
			'pattern'  => '{date}.{slug}',
			'count'    => 2,
			'examples' => [['path' => '_posts/one.md', 'to' => '_posts/2026-01-02.one.md'], ['path' => '_posts/two.md', 'to' => '_posts/2025-03-04.two.md']],
			'skipped'  => 0
		]], $names, 'Without a publish date, when it was updated (D-514).');
		$this->assertSame(400, $this->send('POST', '/health/filenames', '{}', ['X-CSRF-Token' => $token])->getStatusCode());

		$renamed = self::json($this->send('POST', '/health/filenames', '{"type": "post"}', ['X-CSRF-Token' => $token]));

		$this->assertSame(['_posts/one.md' => '_posts/2026-01-02.one.md', '_posts/two.md' => '_posts/2025-03-04.two.md'], $renamed['renamed'] ?? null);
		$this->assertSame([], self::json($this->send('POST', '/health', headers: ['X-CSRF-Token' => $token]))['fileNames'] ?? null);
	}

	public function testMovesCollectionEntriesOutOfFolders(): void
	{
		$this->writeTemporaryFile('user/data/types/post.yaml', "folder: _posts\n");
		$this->writeTemporaryFile('user/content/_posts/2024/old.md', "---\ntitle: Old\nid: 0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e03\n---\n");
		$this->site(['owner']);
		$token = $this->token();

		$this->assertSame(['count' => 1, 'examples' => [['path' => '_posts/2024/old.md', 'to' => '_posts/old.md']]], self::json($this->send('POST', '/health', headers: ['X-CSRF-Token' => $token]))['flat'] ?? null);

		$moved = self::json($this->send('POST', '/health/flatten', '{}', ['X-CSRF-Token' => $token]));

		$this->assertSame(['_posts/2024/old.md' => '_posts/old.md'], $moved['renamed'] ?? null);
		$this->assertSame(['count' => 0, 'examples' => []], self::json($this->send('POST', '/health', headers: ['X-CSRF-Token' => $token]))['flat'] ?? null);
	}

	public function testFixesMediaIds(): void
	{
		$png = (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);

		$this->writeTemporaryFile('user/media/2026/lake.png', $png);
		$this->writeTemporaryFile('user/media/2026/copy.png', $png);
		$this->writeTemporaryFile('user/media/2026/new.png', $png);
		$this->writeTemporaryFile('user/data/media/2026/lake.png.yml', "id: " . self::LIVE . "\n");
		$this->writeTemporaryFile('user/data/media/2026/copy.png.yml', "alt: A copy\nid: " . self::LIVE . "\n");
		$this->site(['owner']);
		$token = $this->token();

		$this->assertSame(['missing' => ['2026/new.png'], 'duplicates' => [['id' => self::LIVE, 'paths' => ['2026/copy.png', '2026/lake.png']]]], self::json($this->send('POST', '/health', headers: ['X-CSRF-Token' => $token]))['mediaIds'] ?? null, 'Media ids, by path in the media folder (D-487).');

		$assigned = self::json($this->send('POST', '/health/media-ids', '{}', ['X-CSRF-Token' => $token]))['assigned'] ?? null;

		$this->assertIsArray($assigned);
		$this->assertSame(['2026/new.png'], array_keys($assigned));
		$this->assertSame(422, $this->send('POST', '/health/media-ids/keep', '{"path": "2026/new.png"}', ['X-CSRF-Token' => $token])->getStatusCode(), 'Only a file that shares its id.');
		$this->assertSame(400, $this->send('POST', '/health/media-ids/keep', '{}', ['X-CSRF-Token' => $token])->getStatusCode());

		$kept = self::json($this->send('POST', '/health/media-ids/keep', '{"path": "2026/lake.png"}', ['X-CSRF-Token' => $token]))['assigned'] ?? null;

		$this->assertIsArray($kept);
		$this->assertSame(['2026/copy.png'], array_keys($kept));
		$this->assertSame(['missing' => [], 'duplicates' => []], self::json($this->send('POST', '/health', headers: ['X-CSRF-Token' => $token]))['mediaIds'] ?? null);
	}

	public function testRecordsImageSizes(): void
	{
		foreach (['photo.png' => [60, 40], 'photo-30x20.png' => [30, 20]] as $name => [$width, $height]) {
			$image = imagecreatetruecolor($width, $height);
			$this->assertNotFalse($image);
			$this->writeTemporaryFile("user/media/2019/{$name}", '');
			imagepng($image, $this->temporaryDirectory() . "/user/media/2019/{$name}");
		}

		$this->site(['owner']);
		$token = $this->token();

		$this->assertSame(['sizes' => 1, 'images' => 1, 'stale' => 0], self::json($this->send('POST', '/health', headers: ['X-CSRF-Token' => $token]))['mediaSizes'] ?? null, 'Sizes found by rule, not yet recorded (D-488).');

		$recorded = self::json($this->send('POST', '/health/media-sizes', '{}', ['X-CSRF-Token' => $token]));

		$this->assertSame(['2019/photo.png' => ['2019/photo-30x20.png']], $recorded['recorded'] ?? null);
		$this->assertSame(['sizes' => 0, 'images' => 0, 'stale' => 0], self::json($this->send('POST', '/health', headers: ['X-CSRF-Token' => $token]))['mediaSizes'] ?? null);
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
