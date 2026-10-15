<?php

/**
 * Field library tests.
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
use Blush\Field\ControlFactory;
use Blush\Field\Field;
use Blush\Field\FieldConfig;
use Blush\Field\FieldControls;
use Blush\Field\FieldFactory;
use Blush\Field\FieldLayout;
use Blush\Field\FieldLibrary;
use Blush\Field\FieldRegistrar;
use Blush\Field\FieldRegistry;
use Blush\Field\FieldSection;
use Blush\Field\Fields\TextField;
use Blush\Field\InvalidSchema;
use Blush\Field\LibraryField;
use Blush\Field\PlacedField;
use Blush\Field\RegisteredField;
use Blush\Field\RegisteredFields;
use Blush\Field\TargetKey;
use Blush\Field\TextFormat;

#[CoversClass(LibraryField::class)]
#[CoversClass(FieldLibrary::class)]
#[CoversClass(FieldLayout::class)]
#[CoversClass(PlacedField::class)]
#[CoversClass(FieldSection::class)]
#[CoversClass(TargetKey::class)]
#[CoversClass(FieldConfig::class)]
#[CoversClass(RegisteredFields::class)]
#[CoversClass(ControlFactory::class)]
#[CoversClass(FieldControls::class)]
final class FieldLibraryTest extends TestCase
{
	private FieldFactory $fields;

	protected function setUp(): void
	{
		$registry = new FieldRegistry();
		new FieldRegistrar($registry)->register();
		$this->fields = new FieldFactory($registry, ControlFactory::core());
	}

	public function testReadsALibraryField(): void
	{
		$field = LibraryField::fromArray([
			'$schema' => '../../../vendor/blush-dev/framework/resources/schemas/field.schema.json',
			'name'    => 'oven',
			'type'    => 'enum',
			'label'   => 'Oven',
			'options' => ['gas', 'electric'],
			'control' => ['name' => 'radios']
		], $this->fields);

		$this->assertSame('oven', $field->name());
		$this->assertSame('Oven', $field->field->label);
		$this->assertArrayNotHasKey('control', $field->field->toArray(), 'The field holds no control (D-795).');
		$this->assertSame(['oven'], array_keys($field->controls->controls));
		$this->assertSame(['name' => 'radios'], $field->toArray()['control'] ?? null);
		$this->assertEquals($field, LibraryField::fromArray($field->toArray(), $this->fields), 'The file round-trips.');
	}

	public function testKeepsAnObjectsControlsByPath(): void
	{
		$field = LibraryField::fromArray(['name' => 'steps', 'type' => 'list', 'item' => ['type' => 'object', 'fields' => [['name' => 'step', 'control' => ['name' => 'textarea']]]], 'control' => ['name' => 'list', 'itemLabel' => 'Step']], $this->fields);

		$this->assertSame(['steps', 'steps.*.step'], array_keys($field->controls->controls));
		$this->assertEquals($field, LibraryField::fromArray($field->toArray(), $this->fields));

		$site = LibraryField::fromArray(['name' => 'site', 'control' => ['name' => 'link']], $this->fields);
		$this->assertSame(TextFormat::Url, $site->field instanceof TextField ? $site->field->format : null, 'A control still names its type and what it implies (D-769).');
	}

	public function testRefusesWhatIsntALibraryField(): void
	{
		$cases = [
			fn (): LibraryField => LibraryField::fromArray(['name' => 'Oven'], $this->fields),
			fn (): LibraryField => LibraryField::fromArray(['name' => 'og:title'], $this->fields),
			fn (): LibraryField => LibraryField::fromArray(['name' => '1st'], $this->fields),
			fn (): LibraryField => LibraryField::fromArray(['name' => 'new'], $this->fields),
			fn (): LibraryField => LibraryField::fromArray(['name' => 'oven', 'type' => 'nope'], $this->fields),
			fn (): LibraryField => LibraryField::fromArray(['name' => 'oven', 'control' => 'radios'], $this->fields),
			fn (): LibraryField => LibraryField::fromArray(['name' => 'oven', 'type' => 'text', 'control' => ['name' => 'toggle']], $this->fields)
		];

		foreach ($cases as $build) {
			try {
				$build();
				$this->fail('The field should be invalid.');
			} catch (InvalidSchema) {
				$this->addToAssertionCount(1);
			}
		}
	}

	public function testReadsALayout(): void
	{
		$data   = ['difficulty', ['field' => 'cook_time', 'label' => 'Cooking time', 'required' => true], ['section' => 'Timing', 'description' => 'In minutes.', 'fields' => ['prep_time', ['field' => 'rest', 'description' => 'Before serving.']]]];
		$layout = FieldLayout::fromArray($data, 'Recipes');

		$this->assertSame(['difficulty', 'cook_time', 'prep_time', 'rest'], $layout->names());
		$this->assertSame(['difficulty', 'cook_time'], array_map(static fn (PlacedField $field): string => $field->name, $layout->loose()));
		$this->assertSame(['Timing'], array_map(static fn (FieldSection $section): string => $section->label, $layout->sections()));
		$this->assertSame('Cooking time', $layout->find('cook_time')?->label);
		$this->assertTrue($layout->has('rest'));
		$this->assertFalse($layout->has('servings'));
		$this->assertSame($data, $layout->toArray(), 'A field the type changes nothing about is its name alone.');
		$this->assertTrue(FieldLayout::fromArray(null, 'Recipes')->isEmpty());
	}

	public function testTakesAFieldOffALayoutKeepingItsSection(): void
	{
		$layout = FieldLayout::fromArray(['a', ['section' => 'More', 'fields' => ['b']]], 'Recipes');

		$this->assertSame([['section' => 'More', 'fields' => ['b']]], $layout->without('a')->toArray());
		$this->assertSame(['a', ['section' => 'More', 'fields' => []]], $layout->without('b')->toArray(), 'A section stays without its fields.');
	}

	public function testRefusesWhatIsntALayout(): void
	{
		$cases = [
			'not a list'        => ['a' => 'b'],
			'placed twice'      => ['a', ['section' => 'More', 'fields' => ['a']]],
			'a bad name'        => ['Not A Key'],
			'an unknown key'    => [['field' => 'a', 'control' => 'radios']],
			'a required string' => [['field' => 'a', 'required' => 'yes']],
			'no heading'        => [['section' => ' ', 'fields' => []]],
			'a section inside'  => [['section' => 'One', 'fields' => [['section' => 'Two', 'fields' => []]]]],
			'not a field'       => [42]
		];

		foreach ($cases as $why => $data) {
			try {
				FieldLayout::fromArray($data, 'Recipes');
				$this->fail("Refused: {$why}.");
			} catch (InvalidSchema) {
				$this->addToAssertionCount(1);
			}
		}
	}

	public function testAPlaceChangesOnlyHowAFieldIsAskedFor(): void
	{
		$field = new TextField('subtitle')->labeled('Subtitle')->described('A line under the title.');

		$this->assertSame($field, new PlacedField('subtitle')->apply($field), 'Nothing changed.');

		$placed = new PlacedField('subtitle', 'Tagline', 'Under the name.', true)->apply($field);

		$this->assertSame(['Tagline', 'Under the name.', true], [$placed->label, $placed->description, $placed->required]);
		$this->assertSame('text', $placed->type());
		$this->assertSame(['field' => 'subtitle', 'required' => false], new PlacedField('subtitle', required: false)->toArray());
		$this->assertNull(PlacedField::fromArray(['field' => 'subtitle', 'label' => '  '], 'Posts')->label, 'An empty label is the field\'s.');
	}

	public function testTheLibraryGivesALayoutsFieldsAsItAsksForThem(): void
	{
		$library = new FieldLibrary([
			'servings' => new LibraryField(new TextField('servings')->labeled('Servings')),
			'notes'    => new LibraryField(new TextField('notes'))
		]);
		$layout  = FieldLayout::fromArray([['field' => 'servings', 'required' => true], 'missing', ['section' => 'More', 'fields' => ['notes']]], 'Recipes');
		$placed  = $library->placed($layout);

		$this->assertSame(['servings', 'notes'], array_keys($placed), 'In its order; a name the library doesn\'t have is left out.');
		$this->assertTrue($placed['servings']->required);
		$this->assertFalse($library->find('servings')?->field->required, 'The library\'s field is as it was.');
		$this->assertSame(['notes', 'servings'], array_keys($library->all()), 'By name.');
		$this->assertCount(2, $library);
		$this->assertEquals($library, FieldLibrary::fromArray($library->toArray(), $this->fields));
	}

	public function testTargetKeysTakeInEveryPlaceOfAKind(): void
	{
		$this->assertTrue(TargetKey::matches(['type:*'], 'type:post'));
		$this->assertTrue(TargetKey::matches(['type:post'], 'type:post'));
		$this->assertFalse(TargetKey::matches(['type:*'], 'media:image'));
		$this->assertFalse(TargetKey::matches(['type:recipe'], 'type:post'));
		$this->assertSame('media', TargetKey::kindOf('media:image'));
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

	public function testNestsTheFormsShape(): void
	{
		$this->assertSame(
			[['name' => 'unit', 'type' => 'text', 'control' => ['name' => 'text', 'placeholder' => 'oz.']], ['name' => 'list', 'type' => 'list', 'item' => ['type' => 'enum', 'control' => ['name' => 'select']]]],
			ControlFactory::core()->nest([['name' => 'unit', 'type' => 'text', 'control' => 'text', 'placeholder' => 'oz.'], ['name' => 'list', 'type' => 'list', 'item' => ['type' => 'enum', 'control' => 'select']]])
		);
	}

	public function testConfigOnlyConfigures(): void
	{
		$config = FieldConfig::fromArray(['library' => false]);

		$this->assertFalse($config->library);
		$this->assertEquals($config, FieldConfig::fromArray($config->toArray()));

		$this->expectException(InvalidConfig::class);

		FieldConfig::fromArray(['dataSets' => false]);
	}
}
