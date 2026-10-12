<?php

/**
 * Field set tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Config\InvalidConfig;
use Blush\Field\Field;
use Blush\Field\FieldConfig;
use Blush\Field\FieldControls;
use Blush\Field\ControlFactory;
use Blush\Field\FieldFactory;
use Blush\Field\FieldRegistrar;
use Blush\Field\FieldRegistry;
use Blush\Field\FieldSet;
use Blush\Field\FieldSets;
use Blush\Field\Fields\TextField;
use Blush\Field\InvalidSchema;
use Blush\Field\TextFormat;
use Blush\Field\RegisteredField;
use Blush\Field\RegisteredFields;

#[CoversClass(FieldSet::class)]
#[CoversClass(FieldSets::class)]
#[CoversClass(FieldConfig::class)]
#[CoversClass(RegisteredFields::class)]
#[CoversClass(ControlFactory::class)]
#[CoversClass(FieldControls::class)]
final class FieldSetTest extends TestCase
{
	private FieldFactory $fields;

	protected function setUp(): void
	{
		$registry = new FieldRegistry();
		new FieldRegistrar($registry)->register();
		$this->fields = new FieldFactory($registry, ControlFactory::core());
	}

	public function testReadsADefinition(): void
	{
		$set = FieldSet::fromArray([
			'$schema'     => '../../vendor/blush-dev/framework/resources/schemas/field-set.schema.json',
			'name'        => 'seo',
			'label'       => 'SEO',
			'description' => 'How the entry appears in search results.',
			'targets'     => 'type:post',
			'fields'      => ['meta_title' => [], 'noindex' => ['type' => 'bool']]
		], $this->fields);

		$this->assertSame('SEO', $set->label);
		$this->assertSame(['type:post'], $set->targets, 'One target counts as a list of one.');
		$this->assertSame(['meta_title', 'noindex'], array_keys($set->schema->fields));
		$this->assertTrue($set->attachesTo('type:post'));
		$this->assertFalse($set->attachesTo('type:page'));
		$this->assertEquals($set, FieldSet::fromArray($set->toArray(), $this->fields));
	}

	public function testHasAPosition(): void
	{
		$set = FieldSet::fromArray(['name' => 'seo', 'targets' => ['type:post'], 'position' => 5], $this->fields);

		$this->assertSame(5, $set->position);
		$this->assertSame(5, $set->toArray()['position'] ?? null);
		$this->assertArrayNotHasKey('position', FieldSet::fromArray(['name' => 'seo'], $this->fields)->toArray(), 'The default is left out.');
		$this->assertSame(FieldSet::POSITION, FieldSet::fromArray(['name' => 'seo'], $this->fields)->position);

		$this->expectException(InvalidSchema::class);

		FieldSet::fromArray(['name' => 'seo', 'position' => 1.5], $this->fields);
	}

	public function testNamesASlotOrLeavesItToItsKind(): void
	{
		$content = FieldSet::fromArray(['name' => 'recipe', 'targets' => ['type:recipe'], 'slot' => 'content'], $this->fields);

		$this->assertSame('content', $content->slot);
		$this->assertSame('type', $content->kind());
		$this->assertSame('content', $content->toArray()['slot'] ?? null);
		$this->assertNull(new FieldSet('seo')->slot, 'Its kind\'s default.');
		$this->assertNull(new FieldSet('seo')->kind());
		$this->assertArrayNotHasKey('slot', new FieldSet('seo')->toArray());
		$this->assertEquals($content, FieldSet::fromArray($content->toArray(), $this->fields));
	}

	public function testTargetsAreOneKind(): void
	{
		$this->expectException(InvalidSchema::class);
		$this->expectExceptionMessage('Field set "seo" targets more than one kind of place (type, settings); a set\'s targets are all one kind, so make a set for each.');

		new FieldSet('seo', [], ['type:post', 'settings:general']);
	}

	public function testLabelsItselfFromItsName(): void
	{
		$set = new FieldSet('recipe_extras');

		$this->assertSame('Recipe extras', $set->label);
		$this->assertArrayNotHasKey('label', $set->toArray());
	}

	public function testASetCanBeOnEveryPlaceOfAKind(): void
	{
		$every = new FieldSet('notes', [], ['type:*']);
		$some  = new FieldSet('kitchen', [], ['type:recipe']);

		$this->assertTrue($every->attachesTo('type:post'));
		$this->assertTrue($every->isEverywhere());
		$this->assertFalse($every->attachesTo('media:image'));
		$this->assertFalse($some->attachesTo('type:post'));
		$this->assertFalse($some->isEverywhere());
		$this->assertSame(['post', 'recipe'], $every->namesOf('type', ['post', 'recipe']));
		$this->assertSame(['recipe'], $some->namesOf('type', ['post', 'recipe']));
	}

	public function testCoresRegisteredFieldsArentAttachedAfterAPlacesOwnFields(): void
	{
		$registered = new RegisteredFields([
			new RegisteredField(RegisteredField::CORE, new TextField('title'), ['type:*']),
			new RegisteredField('acme/seo', new TextField('meta_title'), ['type:post'])
		]);

		$this->assertSame(['meta_title'], array_map(static fn (RegisteredField $field): string => $field->field->name, $registered->for('type:post')));
		$this->assertSame([], $registered->for('type:page'));
		$this->assertSame(['title'], array_map(static fn (Field $field): string => $field->name, $registered->core('type:page')));
		$this->assertEquals(new RegisteredFields([$registered->fields[1]]), RegisteredFields::fromArray($registered->toArray(), $this->fields), 'Core\'s are left out of the cache.');
	}

	public function testKeepsEachFieldsControlBesideIt(): void
	{
		$set = FieldSet::fromArray(['name' => 'kitchen', 'fields' => [
			['name' => 'oven', 'type' => 'enum', 'options' => ['gas', 'electric'], 'control' => ['name' => 'radios']],
			['name' => 'site', 'control' => ['name' => 'link', 'placeholder' => 'https://']],
			['name' => 'steps', 'type' => 'list', 'item' => ['type' => 'object', 'fields' => [['name' => 'step', 'control' => ['name' => 'textarea']]]], 'control' => ['name' => 'repeater', 'itemLabel' => 'Step']]
		]], $this->fields);

		$this->assertArrayNotHasKey('control', $set->schema->field('oven')?->toArray() ?? [], 'The field holds no control (D-795).');
		$this->assertSame(['oven', 'site', 'steps', 'steps.*.step'], array_keys($set->controls->controls));
		$this->assertSame(TextFormat::Url, $set->schema->field('site') instanceof TextField ? $set->schema->field('site')->format : null, 'A control still names its type and what it implies (D-769).');
		$this->assertSame(['name' => 'link', 'placeholder' => 'https://'], $set->toArray()['fields'][1]['control'] ?? null);
		$this->assertEquals($set, FieldSet::fromArray($set->toArray(), $this->fields), 'The file round-trips.');
	}

	public function testNestsTheFormsShape(): void
	{
		$this->assertSame(
			[['name' => 'unit', 'type' => 'text', 'control' => ['name' => 'text', 'placeholder' => 'oz.']], ['name' => 'list', 'type' => 'list', 'item' => ['type' => 'enum', 'control' => ['name' => 'select']]]],
			ControlFactory::core()->nest([['name' => 'unit', 'type' => 'text', 'control' => 'text', 'placeholder' => 'oz.'], ['name' => 'list', 'type' => 'list', 'item' => ['type' => 'enum', 'control' => 'select']]])
		);
	}

	public function testRefusesAControlByItsKeyAlone(): void
	{
		$this->expectException(InvalidSchema::class);
		$this->expectExceptionMessage('Field "oven" "control" must be an object with the control\'s "name", such as {"name": "radios"}.');

		FieldSet::fromArray(['name' => 'kitchen', 'fields' => [['name' => 'oven', 'control' => 'radios']]], $this->fields);
	}

	public function testRefusesWhatIsntASet(): void
	{
		$cases = [
			static fn (): FieldSet => new FieldSet('SEO'),
			static fn (): FieldSet => new FieldSet('acme/seo'),
			static fn (): FieldSet => new FieldSet('seo', [], ['type:**']),
			static fn (): FieldSet => new FieldSet('seo', [], ['post']),
			static fn (): FieldSet => new FieldSet('seo', [new TextField('a'), new TextField('a')]),
			fn (): FieldSet => FieldSet::fromArray(['name' => 'seo', 'type' => 'post'], $this->fields),
			fn (): FieldSet => FieldSet::fromArray(['name' => 'seo', 'fields' => [['type' => 'nope', 'name' => 'x']]], $this->fields)
		];

		foreach ($cases as $build) {
			try {
				$build();
				$this->fail('The set should be invalid.');
			} catch (InvalidSchema) {
				$this->addToAssertionCount(1);
			}
		}
	}

	public function testSetsAreFoundByTargetInNameOrder(): void
	{
		$sets = new FieldSets([
			'social' => new FieldSet('social', [], ['type:post']),
			'about'  => new FieldSet('about', [], ['type:post', 'type:page']),
			'shop'   => new FieldSet('shop', [], ['type:product'])
		]);

		$this->assertSame(['about', 'social'], array_column($sets->for('type:post'), 'name'));
		$this->assertSame(['about', 'shop', 'social'], array_keys($sets->all()));
		$this->assertEquals($sets, FieldSets::fromArray($sets->toArray(), $this->fields));
	}

	public function testConfigOnlyConfigures(): void
	{
		$config = FieldConfig::fromArray(['dataSets' => false]);

		$this->assertFalse($config->dataSets);
		$this->assertEquals($config, FieldConfig::fromArray($config->toArray()));

		$this->expectException(InvalidConfig::class);

		FieldConfig::fromArray(['sets' => []]);
	}
}
