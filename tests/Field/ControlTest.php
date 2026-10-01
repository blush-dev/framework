<?php

/**
 * Field control tests.
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
use Blush\Field\Control;
use Blush\Field\Field;
use Blush\Field\FieldFactory;
use Blush\Field\FieldRegistrar;
use Blush\Field\FieldRegistry;
use Blush\Field\FieldType;
use Blush\Field\Fields\EnumField;
use Blush\Field\Fields\ListField;
use Blush\Field\Fields\MarkdownField;
use Blush\Field\Fields\ReferenceField;
use Blush\Field\Fields\TextField;
use Blush\Field\InvalidSchema;
use Blush\Tests\Fixtures\Content\ColorField;

#[CoversClass(Control::class)]
#[CoversClass(Field::class)]
#[CoversClass(FieldFactory::class)]
#[CoversClass(ListField::class)]
#[CoversClass(ReferenceField::class)]
final class ControlTest extends TestCase
{
	private FieldFactory $fields;

	protected function setUp(): void
	{
		$registry = new FieldRegistry(['color' => ColorField::class]);
		new FieldRegistrar($registry)->register();
		$this->fields = new FieldFactory($registry);
	}

	public function testEveryBuiltInTypeDescribesItself(): void
	{
		foreach (FieldType::cases() as $type) {
			$class = $type->className();

			$this->assertNotSame('', $class::typeLabel(), $type->value);
			$this->assertNotSame('', $class::typeDescription(), $type->value);
			$this->assertNotSame([], $class::controls(), $type->value);
		}
	}

	public function testATypeWithoutControlsIsReadOnly(): void
	{
		$field = new ColorField('accent');

		$this->assertSame('', ColorField::typeLabel());
		$this->assertSame([Control::Readonly], ColorField::controls());
		$this->assertSame(Control::Readonly, $field->editedWith());
	}

	public function testAFieldIsEditedWithItsTypesDefaultControl(): void
	{
		$this->assertSame(Control::Text, new TextField('title')->editedWith());
		$this->assertSame(Control::Textarea, new MarkdownField('bio')->editedWith());
		$this->assertSame(Control::Select, new EnumField('size', ['s', 'm'])->editedWith());
	}

	public function testAFieldMayPickAnotherOfItsTypesControls(): void
	{
		$field = new EnumField('size', ['s', 'm'])->control(Control::Radios);

		$this->assertSame(Control::Radios, $field->editedWith());
		$this->assertSame('radios', $field->toArray()['control'] ?? null);
		$this->assertArrayNotHasKey('control', new EnumField('size', ['s'])->toArray(), 'The default is left out of definitions.');
	}

	public function testAFieldRefusesAControlItsTypeDoesNotHave(): void
	{
		$this->expectException(InvalidSchema::class);
		$this->expectExceptionMessage('Field "title" can\'t use the "checkbox" control; it can use text, textarea, mono.');

		(void) new TextField('title')->control(Control::Checkbox);
	}

	public function testAReferenceNeedsATypeToPickFrom(): void
	{
		$this->assertSame(Control::Reference, new ReferenceField('cast', 'actor')->editedWith());
		$this->assertSame(Control::Mono, new ReferenceField('cast')->editedWith());
		$this->assertFalse(new ReferenceField('cast')->canUse(Control::Reference));
	}

	public function testAListsControlsFollowItsItems(): void
	{
		$tags    = new ListField('tags');
		$choices = new ListField('days', new EnumField('', ['mon', 'tue']));
		$bios    = new ListField('bios', new MarkdownField());

		$this->assertSame(Control::Lines, $tags->editedWith());
		$this->assertFalse($tags->canUse(Control::Checks), 'Checkboxes need choices.');
		$this->assertTrue($choices->canUse(Control::Checks));
		$this->assertSame(Control::Readonly, $bios->editedWith(), 'Formatted text doesn\'t fit on a line.');
		$this->assertSame(Control::Readonly, new ListField('nested', new ListField())->editedWith());
	}

	public function testDefinitionsNameAControl(): void
	{
		$field = $this->fields->fromArray(['name' => 'days', 'type' => 'list', 'control' => 'checks', 'item' => ['type' => 'enum', 'options' => ['mon', 'tue']]]);

		$this->assertSame(Control::Checks, $field->editedWith());
		$this->assertEquals($field, $this->fields->fromArray($field->toArray()));
	}

	public function testDefinitionsRefuseAnUnknownOrUnusableControl(): void
	{
		$cases = [
			['name' => 'title', 'control' => 'wysiwyg'],
			['name' => 'title', 'control' => 'radios'],
			['name' => 'tags', 'type' => 'list', 'control' => 'checks'],
			['name' => 'title', 'control' => true]
		];

		foreach ($cases as $definition) {
			try {
				$this->fields->fromArray($definition);
				$this->fail('The definition should be invalid.');
			} catch (InvalidSchema) {
				$this->addToAssertionCount(1);
			}
		}
	}

	public function testFormsGetTheControlAFieldIsEditedWith(): void
	{
		$form = new ColorField('accent')->toForm();

		$this->assertSame('readonly', $form['control'] ?? null);
		$this->assertArrayNotHasKey('class', $form);
		$this->assertSame('mono', new ReferenceField('cast')->toForm()['control'] ?? null);
	}

	public function testDefinitionsMayBeAListOrAMap(): void
	{
		$list = $this->fields->schema([['name' => 'servings', 'type' => 'number'], ['name' => 'cuisine']]);
		$map  = $this->fields->schema(['servings' => ['type' => 'number'], 'cuisine' => ['name' => 'ignored']]);

		$this->assertEquals($list, $map);
		$this->assertSame(['servings', 'cuisine'], array_keys($map->fields));
	}

	public function testDefinitionsMustBeMaps(): void
	{
		$this->expectException(InvalidSchema::class);

		FieldFactory::definitions(['servings' => 'number']);
	}
}
