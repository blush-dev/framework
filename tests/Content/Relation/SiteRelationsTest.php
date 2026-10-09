<?php

/**
 * Site relations tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Content\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Content\Entries;
use Blush\Content\Entry\Entry;
use Blush\Content\Index\ContentIndex;
use Blush\Content\Index\Indexer;
use Blush\Content\Index\IndexSnapshot;
use Blush\Content\Lint\Linter;
use Blush\Content\Relation\EntryRelations;
use Blush\Content\Relation\LinkBuilder;
use Blush\Content\Relation\LinkReport;
use Blush\Content\Relation\LinkResolver;
use Blush\Content\Relation\Relation;
use Blush\Content\Relation\RelationCompiler;
use Blush\Content\Relation\RelationKind;
use Blush\Content\Relation\RelationProblem;
use Blush\Content\Relation\Relations;
use Blush\Content\Relation\Resolution;
use Blush\Content\Relation\SnapshotTargets;
use Blush\Content\Relation\TranslationRule;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\InvalidContentType;
use Blush\Core\Application;
use Blush\Field\Violation;
use Blush\Tests\Content\BuildsContentSite;

#[CoversClass(RelationCompiler::class)]
#[CoversClass(LinkBuilder::class)]
#[CoversClass(LinkReport::class)]
#[CoversClass(LinkResolver::class)]
#[CoversClass(Resolution::class)]
#[CoversClass(SnapshotTargets::class)]
#[CoversClass(EntryRelations::class)]
final class SiteRelationsTest extends TestCase
{
	use BuildsContentSite;

	private Application $app;

	private Relations $relations;

	private IndexSnapshot $snapshot;

	private LinkReport $report;

	protected function setUp(): void
	{
		$this->writeTemporaryFile('config/app.php', <<<'PHP'
			<?php

			declare(strict_types=1);

			return Blush\Core\AppConfig::fromArray(['timezone' => 'America/Chicago', 'languages' => ['fr' => 'fr_FR']]);
			PHP);

		$this->contentConfig([
			'types' => [
				'post'     => ['routing' => ['prefix' => 'archives']],
				'category' => ['urls' => ['prefix' => 'topics'], 'order' => 'position', 'hierarchical' => true],
				'movie'    => ['urls' => ['prefix' => 'movies'], 'fields' => [
					['name' => 'actors', 'type' => 'reference', 'to' => 'person'],
					['name' => 'director', 'type' => 'reference', 'to' => 'person', 'multiple' => false, 'required' => true]
				]],
				'person'   => ['urls' => ['prefix' => 'people']]
			],
			'relations' => [
				'category' => ['kind' => 'classify', 'from' => ['post'], 'to' => ['category'], 'create' => true],
				'authors'  => ['kind' => 'credit', 'from' => ['post'], 'to' => ['profile'], 'aliases' => ['author']]
			]
		]);

		$this->entry('index.md', 'title: Home');
		$this->entry('about/index.md', 'title: About');
		$this->entry('about/team.md', 'title: Team');
		$this->entry('contact.md', 'title: Contact');
		$this->entry('about/history.md', "title: History\nrefs:\n  parent:\n    about: " . self::idFor('contact.md'));
		$this->entry('_category/art.md', "title: Art\ncategory: art");
		$this->entry('_category/painting.md', "title: Painting\nparent: art");
		$this->entry('_category/painting.fr.md', "title: Peinture\nslug: peinture\ntranslation_of: " . self::idFor('_category/painting.md'));
		$this->entry('_category/old.md', 'title: Old');
		$this->entry('_category/watercolor.md', "title: Watercolor\nparent: arts\nrefs:\n  parent:\n    arts: " . self::idFor('_category/art.md'));
		$this->entry('_profile/jane.md', 'title: Jane');
		$this->entry('_profile/sam.md', 'title: Sam');
		$this->entry('_profile/guest.md', 'title: Guest');
		$this->entry('_post/a.md', "title: A\npublished: 2026-01-01\ncategory: [painting, Old]\nauthors: [jane, sam]\nrelated: [b]");
		$this->entry('_post/a.fr.md', "title: A en français\npublished: 2026-01-01\nauthors: [guest]");
		$this->entry('_post/b.md', "title: B\npublished: 2026-01-02\ncategory: [paint]\nauthor: jane\nrefs:\n  category:\n    paint: " . self::idFor('_category/painting.md'));
		$this->entry('_post/b.fr.md', "title: B en français\npublished: 2026-01-02\ncategory: old");
		$this->entry('_post/c.md', "title: C\npublished: 2026-01-03\ncategory: [" . strtoupper(self::idFor('_category/art.md')) . ", nowhere]\nrelated: [c]");
		$this->entry('_post/d.md', "title: D\npublished: 2026-01-04\nstatus: draft\ncategory: art");
		$this->entry('_movie/big.md', "title: Big\npublished: 2026-01-01\nactors: [tom, meg, tom]\ndirector: penny");
		$this->entry('_movie/small.md', "title: Small\npublished: 2026-01-01\nactors: tom\nrefs:\n  actors:\n    tom: " . self::idFor('_person/tom.md') . "\n    gone: " . self::idFor('_person/meg.md'));
		$this->entry('_person/tom.md', 'title: Tom');
		$this->entry('_person/meg.md', 'title: Meg');
		$this->entry('_person/penny.md', 'title: Penny');

		$this->app = $this->site();

		$related = new Relation('related', RelationKind::Reference, ['post'], ['post'], symmetric: true);
		$types   = $this->app->container()->make(ContentTypes::class);

		// As a relation source would add it.
		$this->relations = new RelationCompiler()->compile($types, [$related]);
		$this->app->container()->instance(Relations::class, $this->relations);
		$this->app->container()->make(Indexer::class)->index(full: true);

		$this->snapshot = $this->app->container()->make(ContentIndex::class)->snapshot();
		$this->report   = new LinkBuilder()->build($this->snapshot, $this->relations, $types);
	}

	public function testCompilesTodaysTypesAsRelations(): void
	{
		$post = $this->relations->for('post');

		$this->assertSame(['authors', 'category', 'translation_of', 'related'], array_keys($post));
		$this->assertSame(RelationKind::Classify, $post['category']->kind);
		$this->assertSame(['post'], $post['category']->from, 'Defined on its own, from the types it files (D-593).');
		$this->assertTrue($post['category']->inverse !== false && $post['category']->inverse->page, 'Its terms\' pages list what\'s filed under them.');
		$this->assertTrue($post['category']->create);
		$this->assertSame(RelationKind::Credit, $post['authors']->kind);
		$this->assertSame(['author'], $post['authors']->aliases, '1.x\'s `author` keeps working.');
		$this->assertSame(TranslationRule::Add, $post['authors']->translations);
		$this->assertTrue($post['authors']->ordered);

		$this->assertSame(['parent', 'translation_of'], array_keys($this->relations->for('category')), 'Terms nest by `parent` (D-593).');
		$this->assertSame(['parent', 'translation_of'], array_keys($this->relations->for('page')), 'A tree has a parent.');

		$movie = $this->relations->for('movie');

		$this->assertSame(['translation_of', 'actors', 'director'], array_keys($movie));
		$this->assertSame(RelationKind::Reference, $movie['director']->kind);
		$this->assertFalse($movie['director']->multiple);
		$this->assertTrue($movie['director']->isRequired());
		$this->assertSame(['movie.actors', 'movie.director', 'person.translation_of'], array_keys($this->relations->to('person')));
	}

	public function testBuildsLinksBetweenIds(): void
	{
		$graph = $this->report->graph;

		$this->assertSame([$this->id('_category/painting.md'), $this->id('_category/old.md')], $graph->targets($this->id('_post/a.md'), 'category'), 'Labels are slugs, in order.');
		$this->assertSame([$this->id('_profile/jane.md'), $this->id('_profile/sam.md')], $graph->targets($this->id('_post/a.md'), 'authors'));
		$this->assertSame([$this->id('_profile/jane.md')], $graph->targets($this->id('_post/b.md'), 'authors'), 'Read from an alias.');
		$this->assertSame([$this->id('_category/art.md')], $graph->targets($this->id('_category/painting.md'), 'parent'));
		$this->assertSame([$this->id('about/index.md')], $graph->targets($this->id('about/team.md'), 'parent'), 'A tree\'s folder.');
		$this->assertSame([$this->id('_post/a.md')], $graph->targets($this->id('_post/a.fr.md'), 'translation_of'));
		$this->assertSame([$this->id('_category/painting.md')], $graph->targets($this->id('_category/painting.fr.md'), 'translation_of'));
		$this->assertSame([], $graph->targets($this->id('_post/a.md'), 'translation_of'), 'An original has none.');
		$this->assertSame([$this->id('_person/tom.md'), $this->id('_person/meg.md')], $graph->targets($this->id('_movie/big.md'), 'actors'), 'A target named twice links once.');
		$this->assertSame([$this->id('_person/penny.md')], $graph->targets($this->id('_movie/big.md'), 'director'));
		$this->assertSame([$this->id('_post/b.md')], $graph->targets($this->id('_post/a.md'), 'related'), 'Read from undeclared front matter.');
	}

	public function testFollowsRefsAndRewritesBothForms(): void
	{
		$b = $this->report->stale['_post/b.md']['category'];

		$this->assertSame([$this->id('_category/painting.md')], array_map(static fn ($link): string => $link->target, $b->links), 'The id wins over a renamed slug.');
		$this->assertSame(['painting'], $b->written);
		$this->assertSame(['painting' => $this->id('_category/painting.md')], $b->refs);

		$c = $this->report->stale['_post/c.md']['category'];

		$this->assertSame(['art', 'nowhere'], $c->written, 'An id in the written form is written as its slug; a value that finds nothing stays.');
		$this->assertSame(['art' => $this->id('_category/art.md')], $c->refs);

		$small = $this->report->stale['_movie/small.md']['actors'];

		$this->assertSame(['tom' => $this->id('_person/tom.md')], $small->refs, 'Ids for values no longer written are dropped.');
		$this->assertSame('penny', $this->report->stale['_movie/big.md']['director']->value($this->relations->get('movie', 'director')));
		$this->assertArrayHasKey('authors', $this->report->stale['_post/a.md'], 'A file without refs gets them.');

		$watercolor = $this->report->stale['_category/watercolor.md']['parent'];

		$this->assertSame('art', $watercolor->value($this->relations->get('category', 'parent')), 'A parent is filed like any relation: its id wins over a renamed slug (D-591).');
		$this->assertSame(['art' => $this->id('_category/art.md')], $watercolor->refs);
		$this->assertSame(['about' => $this->id('about/index.md')], $this->report->stale['about/team.md']['parent']->refs, 'A tree\'s folder is its written form; its id is filled in.');

		$history = $this->report->stale['about/history.md']['parent'];

		$this->assertSame([$this->id('about/index.md')], array_map(static fn ($link): string => $link->target, $history->links), 'The folder wins over an id a move by hand left behind.');
		$this->assertSame(['about' => $this->id('about/index.md')], $history->refs);
		$this->assertArrayNotHasKey('contact.md', $this->report->stale, 'A top-level page has no parent to file.');

		$problems = array_map(static fn (RelationProblem $problem): string => "{$problem->kind->value} {$problem->key} {$problem->value}", $this->report->problems);

		$this->assertSame(['missing post.category nowhere', 'self post.related c'], $problems);
	}

	public function testReadsRelatedEntries(): void
	{
		$read = $this->app->container()->make(EntryRelations::class);

		$this->assertSame(['Painting', 'Old'], $this->titles($read->related($this->at('_post/a.md'), 'category')));
		$this->assertSame(['Jane', 'Sam'], $this->titles($read->related($this->at('_post/a.md'), 'authors')));
		$this->assertSame(['B'], $this->titles($read->related($this->at('_post/a.md'), 'related')));
		$this->assertSame(['A'], $this->titles($read->related($this->at('_post/b.md'), 'related')), 'Symmetric: B is related to A too.');
		$this->assertSame([], $read->related($this->at('_post/a.md'), 'actors'), 'Not a post\'s relation.');
		$this->assertCount(2, $read->links($this->at('_post/a.md'), 'category'));

		$this->assertSame(['Peinture', 'Old'], $this->titles($read->related($this->at('_post/a.fr.md'), 'category')), 'The original\'s, in its language where a translation is.');
		$this->assertSame(['Jane', 'Sam', 'Guest'], $this->titles($read->related($this->at('_post/a.fr.md'), 'authors')), 'Credits add to the original\'s.');

		$this->assertSame(['C'], $this->titles($read->referencedBy($this->at('_category/art.md'), 'category')), 'Only live entries: D is a draft.');
		$this->assertSame(['Painting', 'Watercolor'], $this->titles($read->referencedBy($this->at('_category/art.md'), 'parent')));
		$this->assertSame(['B', 'A'], $this->titles($read->referencedBy($this->at('_category/painting.md'), 'post.category')), 'Newest first.');
		$this->assertSame(['A en français'], $this->titles($read->referencedBy($this->at('_category/painting.fr.md'), 'category')), 'In French, B en français is under Old only: its own replace its original\'s.');
		$this->assertSame(['B en français', 'A en français'], $this->titles($read->referencedBy($this->at('_category/old.md'), 'category', 'fr')), 'Old has no translation, so it\'s read in French by asking.');
		$this->assertSame(['A'], $this->titles($read->referencedBy($this->at('_category/old.md'), 'category')), 'A translation\'s own link shows only in its language.');
		$this->assertSame(['Old'], $this->titles($read->related($this->at('_post/b.fr.md'), 'category')));
		$this->assertSame(['Big', 'Small'], $this->titles($read->referencedBy($this->at('_person/tom.md'), 'movie.actors')));
		$this->assertSame([], $read->referencedBy($this->at('_person/tom.md'), 'movie.director'));
	}

	public function testTheIndexKeepsTheGraphAndTermsFollowIds(): void
	{
		$content = $this->repository($this->app);

		$this->assertSame($this->report->graph->toArray(), $this->snapshot->graph()->toArray(), 'The index stores the graph.');
		$this->assertSame(['painting'], $this->at('_post/b.md')->terms('category'), 'terms() follows the id in refs through a rename.');
		$this->assertSame(['art'], $this->at('_post/c.md')->terms('category'), 'An id written as a value reads as its slug; a value that links nothing isn\'t a term (D-662).');
		$this->assertSame(['jane'], $this->at('_post/b.md')->terms('profile.authors'));
		$this->assertSame(['jane'], $this->at('_post/b.md')->terms('profile'));
		$this->assertContains('_post/b.md', $this->snapshot->referencing('category', 'painting'));
		$this->assertSame(['B', 'A'], array_map(static fn (Entry $entry): string => $entry->title, $content->query()->type('post')->whereTerm('category', 'painting')->get()->all()), 'whereTerm() too.');
		$this->assertSame(1, $content->termCounts('category')['art'] ?? null, 'C counts for Art by its id; D is a draft.');
	}

	public function testLintReportsRelationProblems(): void
	{
		$report = $this->app->container()->make(Linter::class)->lint();
		$found  = static fn (string $path): array => array_map(static fn (Violation $violation): string => "{$violation->field}: {$violation->message}", $report->files[$path] ?? []);

		$this->assertContains('related: An entry can\'t link to itself.', $found('_post/c.md'));
		$this->assertNotContains('category: "nowhere" names no category.', $found('_post/c.md'), 'A missing term is reported once, by its own check.');
	}

	public function testRefsIsReserved(): void
	{
		$this->contentConfig(['types' => ['post' => ['urls' => ['prefix' => 'posts'], 'fields' => [['name' => 'refs', 'type' => 'text']]]]]);

		$this->expectException(InvalidContentType::class);
		$this->expectExceptionMessage('reserved for the ids of the entry\'s links');

		$this->site()->container()->make(ContentTypes::class)->schema('post');
	}

	/**
	 * Returns the id the content site gave a file.
	 */
	private function id(string $path): string
	{
		return self::idFor($path);
	}

	/**
	 * Returns the entry at a path.
	 */
	private function at(string $path): Entry
	{
		$entry = $this->entryAt($path, $this->app);

		$this->assertNotNull($entry, "No entry at {$path}.");

		return $entry;
	}

	/**
	 * Returns entries' titles.
	 *
	 * @param  list<Entry> $entries
	 * @return list<string>
	 */
	private function titles(array $entries): array
	{
		return array_map(static fn (Entry $entry): string => $entry->title, $entries);
	}
}
