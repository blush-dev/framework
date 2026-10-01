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
use Blush\Field\FieldConfig;
use Blush\Field\FieldFactory;
use Blush\Field\FieldRegistrar;
use Blush\Field\FieldRegistry;
use Blush\Field\FieldSet;
use Blush\Field\FieldSetOrigin;
use Blush\Field\FieldSets;
use Blush\Field\Fields\TextField;
use Blush\Field\InvalidSchema;

#[CoversClass(FieldSet::class)]
#[CoversClass(FieldSets::class)]
#[CoversClass(FieldConfig::class)]
final class FieldSetTest extends TestCase
{
	private FieldFactory $fields;

	protected function setUp(): void
	{
		$registry = new FieldRegistry();
		new FieldRegistrar($registry)->register();
		$this->fields = new FieldFactory($registry);
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

	public function testLabelsItselfFromItsName(): void
	{
		$set = new FieldSet('recipe_extras');

		$this->assertSame('Recipe extras', $set->label);
		$this->assertArrayNotHasKey('label', $set->toArray());
	}

	public function testRefusesWhatIsntASet(): void
	{
		$cases = [
			static fn (): FieldSet => new FieldSet('SEO'),
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
		], ['social' => FieldSetOrigin::Extension, 'about' => FieldSetOrigin::Config, 'shop' => FieldSetOrigin::Data]);

		$this->assertSame(['about', 'social'], array_column($sets->for('type:post'), 'name'));
		$this->assertSame(['about', 'shop', 'social'], array_keys($sets->all()));
		$this->assertSame(FieldSetOrigin::Data, $sets->origin('shop'));
		$this->assertEquals($sets, FieldSets::fromArray($sets->toArray(), $this->fields));
	}

	public function testConfigKeepsArraySetsForLoading(): void
	{
		$config = FieldConfig::fromArray(['sets' => ['seo' => ['targets' => ['type:post']]], 'dataSets' => false]);

		$this->assertSame([], $config->sets);
		$this->assertSame([['name' => 'seo', 'targets' => ['type:post']]], $config->definitions);
		$this->assertFalse($config->dataSets);
		$this->assertEquals($config, FieldConfig::fromArray($config->toArray()));
	}

	public function testConfigRefusesASetTwice(): void
	{
		$this->expectException(InvalidConfig::class);
		$this->expectExceptionMessage('FieldConfig defines the "seo" field set more than once.');

		new FieldConfig(sets: [new FieldSet('seo')], definitions: [['name' => 'seo']]);
	}
}
