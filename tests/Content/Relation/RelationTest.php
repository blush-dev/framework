<?php

/**
 * Relation tests.
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
use Blush\Content\Relation\InvalidRelation;
use Blush\Content\Relation\Inverse;
use Blush\Content\Relation\Link;
use Blush\Content\Relation\ProblemKind;
use Blush\Content\Relation\Refs;
use Blush\Content\Relation\Relation;
use Blush\Content\Relation\RelationChecker;
use Blush\Content\Relation\RelationGraph;
use Blush\Content\Relation\RelationKind;
use Blush\Content\Relation\RelationProblem;
use Blush\Content\Relation\Relations;
use Blush\Content\Relation\TranslationRule;

#[CoversClass(Relation::class)]
#[CoversClass(RelationKind::class)]
#[CoversClass(Inverse::class)]
#[CoversClass(TranslationRule::class)]
#[CoversClass(Refs::class)]
#[CoversClass(Relations::class)]
#[CoversClass(Link::class)]
#[CoversClass(RelationGraph::class)]
#[CoversClass(RelationChecker::class)]
#[CoversClass(RelationProblem::class)]
final class RelationTest extends TestCase
{
	private const string A = '01990000-0000-7000-8000-00000000000a';
	private const string B = '01990000-0000-7000-8000-00000000000b';
	private const string C = '01990000-0000-7000-8000-00000000000c';

	public function testDefinesARelation(): void
	{
		$relation = new Relation('actors', RelationKind::Reference, from: ['movie'], to: ['person'], aliases: ['cast'], ordered: true, min: 1);

		$this->assertSame('actors', $relation->field);
		$this->assertSame(['actors', 'cast'], $relation->keys());
		$this->assertSame('movie.actors', $relation->key('movie'));
		$this->assertTrue($relation->isFrom('movie'));
		$this->assertFalse($relation->isFrom('post'));
		$this->assertTrue($relation->isTo('person'));
		$this->assertTrue($relation->isRequired());
		$this->assertFalse($relation->isHierarchical());
		$this->assertTrue(new Relation('tag', RelationKind::Classify, to: ['tag'])->isFrom('anything'), 'No `from` is every type.');
		$this->assertTrue(new Relation('tag', RelationKind::Classify, to: ['tag'])->inverse !== false && new Relation('tag', RelationKind::Classify, to: ['tag'])->inverse->archive === true, 'Terms list what\'s filed under them unless it says otherwise (D-593).');
		$this->assertSame(1, new Relation('director', RelationKind::Reference, to: ['person'], multiple: false, max: 5)->max, 'One target, whatever max says.');
	}

	public function testRoundTripsAsAnArray(): void
	{
		$relation = new Relation(
			'authors',
			RelationKind::Credit,
			['post'],
			['profile'],
			aliases: ['author'],
			ordered: true,
			min: 1,
			max: 3,
			inverse: new Inverse(label: 'Posts', archive: 'authors', max: 9),
			translations: TranslationRule::Add,
			defaults: ['jane'],
			label: 'Authors'
		);

		$array = $relation->toArray();

		$this->assertSame([
			'name'         => 'authors',
			'kind'         => 'credit',
			'from'         => ['post'],
			'to'           => ['profile'],
			'aliases'      => ['author'],
			'ordered'      => true,
			'min'          => 1,
			'max'          => 3,
			'inverse'      => ['label' => 'Posts', 'archive' => 'authors', 'max' => 9],
			'translations' => 'add',
			'defaults'     => ['jane'],
			'label'        => 'Authors'
		], $array);
		$this->assertEquals($relation, Relation::fromArray($array));
		$this->assertSame(['name' => 'tag', 'kind' => 'classify', 'to' => ['tag'], 'inverse' => false], new Relation('tag', RelationKind::Classify, to: ['tag'], inverse: false)->toArray());
		$this->assertSame(1, Relation::fromArray(['name' => 'x', 'to' => 'y', 'required' => true])->min, '`required` is a min of 1.');

		$listed = Relation::fromArray(['name' => 'tag', 'kind' => 'classify', 'to' => ['tag'], 'inverse' => ['listing' => ['perPage' => 5]]])->inverse;

		$this->assertTrue($listed !== false && $listed->archive === true, 'A classify relation keeps its term pages when only its listing is given.');
	}

	public function testRefusesBadDefinitions(): void
	{
		$cases = [
			static fn (): Relation => new Relation('Actors', RelationKind::Reference, to: ['person']),
			static fn (): Relation => new Relation('actors', RelationKind::Reference),
			static fn (): Relation => new Relation('actors', RelationKind::Reference, to: ['person'], field: 'refs'),
			static fn (): Relation => new Relation('actors', RelationKind::Reference, to: ['person'], aliases: ['id']),
			static fn (): Relation => new Relation('actors', RelationKind::Reference, to: ['person'], min: 3, max: 2),
			static fn (): Relation => new Relation('actors', RelationKind::Reference, to: ['person'], min: -1),
			static fn (): Relation => new Relation('actors', RelationKind::Reference, to: ['person'], multiple: false, defaults: ['a', 'b']),
			static fn (): Relation => new Relation('parent', RelationKind::Parent, ['page'], ['post']),
			static fn (): Relation => new Relation('parent', RelationKind::Parent, ['page'], ['page'], symmetric: true),
			static fn (): Relation => new Relation('related', RelationKind::Reference, ['post'], ['page'], symmetric: true),
			static fn (): Inverse => new Inverse(archive: ''),
			static fn (): Inverse => new Inverse(max: 0),
			static fn (): Relation => Relation::fromArray(['name' => 'x', 'to' => 'y', 'kind' => 'sideways'])
		];

		foreach ($cases as $index => $case) {
			try {
				$case();
				$this->fail("Case {$index} should be refused.");
			} catch (InvalidRelation) {
				$this->addToAssertionCount(1);
			}
		}
	}

	public function testTranslationRules(): void
	{
		$this->assertSame(['a'], TranslationRule::Fallback->apply(['a'], ['b']), 'Its own replace the original\'s.');
		$this->assertSame(['b'], TranslationRule::Fallback->apply([], ['b']));
		$this->assertSame(['b', 'a'], TranslationRule::Add->apply(['a', 'b'], ['b']));
		$this->assertSame([], TranslationRule::Own->apply([], ['b']));
	}

	public function testReadsRefsLeniently(): void
	{
		$refs = Refs::fromValue([
			'tags'    => ['cooking' => strtoupper(self::A), 'baking' => 'not an id', '' => self::B],
			'authors' => 'jane',
			7         => ['x' => self::C]
		]);

		$this->assertSame(['tags' => ['cooking' => self::A]], $refs->toArray(), 'Only written values mapped to ids, lowercased.');
		$this->assertSame(self::A, $refs->idFor('tags', 'cooking'));
		$this->assertNull($refs->idFor('tags', 'baking'));
		$this->assertTrue(Refs::fromValue('nonsense')->isEmpty());
		$this->assertSame(['tags' => ['cooking' => self::A], 'authors' => ['jane' => self::B]], $refs->with('authors', ['jane' => self::B])->toArray());
		$this->assertTrue($refs->with('tags', [])->isEmpty());
	}

	public function testRelationsFitTheTypes(): void
	{
		$relations = new Relations([
			new Relation('category', RelationKind::Classify, to: ['category']),
			new Relation('actors', RelationKind::Reference, ['movie'], ['person'], aliases: ['cast']),
			new Relation('parent', RelationKind::Parent, ['category'], ['category'])
		], ['post', 'movie', 'person', 'category']);

		$this->assertSame(['category', 'actors'], array_keys($relations->for('movie')));
		$this->assertSame(['category'], array_keys($relations->for('post')));
		$this->assertSame('actors', $relations->reading('movie', 'cast')?->name);
		$this->assertSame('parent', $relations->reading('category', 'parent')?->name, 'A parent is filed like any relation (D-591).');
		$this->assertSame('actors', $relations->byKey('movie.actors')?->name);
		$this->assertNull($relations->byKey('post.actors'));
		$this->assertSame(['movie.actors'], array_keys($relations->to('person')));
		$this->assertSame(['post.category', 'movie.category', 'person.category', 'category.category', 'category.parent'], array_keys($relations->to('category')));
		$this->assertEquals($relations, Relations::fromArray($relations->toArray(), $relations->types));

		$cases = [
			[new Relation('actors', RelationKind::Reference, ['movie'], ['robot'])],
			[new Relation('actors', RelationKind::Reference, ['movie'], ['person']), new Relation('actors', RelationKind::Reference, ['movie'], ['person'])],
			[new Relation('actors', RelationKind::Reference, ['movie'], ['person']), new Relation('stars', RelationKind::Reference, ['movie'], ['person'], field: 'actors')],
			[new Relation('category', RelationKind::Classify, to: ['category']), new Relation('topics', RelationKind::Reference, ['post'], ['category'], aliases: ['category'])]
		];

		foreach ($cases as $index => $case) {
			try {
				new Relations($case, ['post', 'movie', 'person', 'category']);
				$this->fail("Case {$index} should be refused.");
			} catch (InvalidRelation) {
				$this->addToAssertionCount(1);
			}
		}
	}

	public function testGraphLooksUpBothWays(): void
	{
		$graph = RelationGraph::build([
			new Link(self::A, 'movie', 'actors', self::C, 'person', 1),
			new Link(self::A, 'movie', 'actors', self::B, 'person', 0),
			new Link(self::B, 'post', 'related', self::C, 'person')
		]);

		$this->assertSame([self::B, self::C], $graph->targets(self::A, 'actors'), 'In position order.');
		$this->assertSame([], $graph->targets(self::A, 'related'));
		$this->assertCount(2, $graph->links(self::A));
		$this->assertSame([self::A, self::B], $graph->sources(self::C));
		$this->assertSame([self::A], $graph->sources(self::C, 'movie.actors'));
		$this->assertSame([self::A], $graph->sources(self::C, 'actors'));
		$this->assertSame([], $graph->sources(self::C, 'post.actors'));
		$this->assertEquals($graph, RelationGraph::fromArray($graph->toArray()));
		$this->assertCount(3, $graph->all());
		$this->assertSame([], RelationGraph::empty()->all());
	}

	public function testChecksTheWholeGraph(): void
	{
		$relations = new Relations([
			new Relation('director', RelationKind::Reference, ['movie'], ['person'], multiple: false, min: 1),
			new Relation('season', RelationKind::Reference, ['episode'], ['season'], inverse: new Inverse(max: 1)),
			new Relation('parent', RelationKind::Parent, ['topic'], ['topic'])
		], ['movie', 'person', 'episode', 'season', 'topic']);

		$d = '01990000-0000-7000-8000-0000000000d0';
		$e = '01990000-0000-7000-8000-0000000000e0';
		$s = '01990000-0000-7000-8000-0000000000f0';
		$t = '01990000-0000-7000-8000-0000000000f1';

		$graph = RelationGraph::build([
			new Link(self::A, 'topic', 'parent', self::B, 'topic'),
			new Link(self::B, 'topic', 'parent', self::A, 'topic'),
			new Link($d, 'episode', 'season', $s, 'season'),
			new Link($e, 'episode', 'season', $s, 'season'),
			new Link($e, 'episode', 'season', $t, 'season', 1)
		]);

		$entries = [self::A => 'topic', self::B => 'topic', self::C => 'movie', '01990000-0000-7000-8000-0000000000c1' => 'movie', $d => 'episode', $e => 'episode', $s => 'season', $t => 'season'];
		$problems = new RelationChecker()->check($relations, $graph, $entries, [self::C]);
		$found    = array_map(static fn (RelationProblem $problem): string => "{$problem->kind->value} {$problem->key} {$problem->source}", $problems);

		$this->assertSame([
			'cycle topic.parent ' . self::A,
			'cycle topic.parent ' . self::B,
			'too-few movie.director ' . self::C,
			'inverse-limit season ' . $s
		], $found, 'A draft movie may go without its director; a live one can\'t.');
		$this->assertSame(ProblemKind::InverseLimit, $problems[3]->kind);
		$this->assertSame('At most 1 may name it in season; 2 do.', $problems[3]->message);
	}
}
