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
use Blush\Field\ControlFactory;
use Blush\Field\ControlRegistrar;
use Blush\Field\ControlRegistry;
use Blush\Field\Controls\Checkboxes;
use Blush\Field\Controls\ChoiceList;
use Blush\Field\Controls\Code;
use Blush\Field\Controls\File;
use Blush\Field\Controls\Group;
use Blush\Field\Controls\Image;
use Blush\Field\Controls\Link;
use Blush\Field\Controls\Markdown;
use Blush\Field\Controls\Radios;
use Blush\Field\Controls\Reference;
use Blush\Field\Controls\Repeater;
use Blush\Field\Controls\Select;
use Blush\Field\Controls\Text;
use Blush\Field\Controls\TextBox;
use Blush\Field\Controls\Toggle;
use Blush\Field\Controls\ViewOnly;
use Blush\Field\ControlType;
use Blush\Field\Field;
use Blush\Field\FieldFactory;
use Blush\Field\FieldRegistrar;
use Blush\Field\FieldRegistry;
use Blush\Field\Fields\BoolField;
use Blush\Field\Fields\EnumField;
use Blush\Field\Fields\ListField;
use Blush\Field\Fields\MarkdownField;
use Blush\Field\Fields\MediaField;
use Blush\Field\Fields\NumberField;
use Blush\Field\Fields\ObjectField;
use Blush\Field\Fields\ReferenceField;
use Blush\Field\Fields\SlugField;
use Blush\Field\Fields\TextField;
use Blush\Field\InvalidSchema;
use Blush\Field\Schema;
use Blush\Field\TextFormat;
use Blush\Media\MediaKind;
use Blush\Tests\Fixtures\Content\ColorField;

#[CoversClass(Control::class)]
#[CoversClass(ControlFactory::class)]
#[CoversClass(ControlRegistrar::class)]
#[CoversClass(ControlRegistry::class)]
#[CoversClass(ChoiceList::class)]
#[CoversClass(Repeater::class)]
#[CoversClass(TextBox::class)]
#[CoversClass(Field::class)]
#[CoversClass(FieldFactory::class)]
final class ControlTest extends TestCase
{
	private FieldFactory $fields;

	private ControlFactory $controls;

	protected function setUp(): void
	{
		$registry = new FieldRegistry(['color' => ColorField::class]);
		new FieldRegistrar($registry)->register();
		$this->controls = ControlFactory::core();
		$this->fields   = new FieldFactory($registry, $this->controls);
	}

	public function testEveryCoreControlDescribesItself(): void
	{
		foreach (ControlType::cases() as $type) {
			$class = $type->className();

			$this->assertSame($type->value, $class::name());
			$this->assertNotSame('', $class::label(), $type->value);
			$this->assertNotSame('', $class::description(), $type->value);

			if ($class::section() !== null) {
				$this->assertNotNull($class::type(), "{$type->value} is offered, so it names the type it stores.");
			}
		}

		$this->assertSame(array_column(ControlType::cases(), 'value'), array_keys($this->controls->all()));
	}

	public function testAFieldIsEditedWithItsTypesDefaultControl(): void
	{
		$cases = [
			[Text::class, new TextField('title')],
			[Link::class, new TextField('site', TextFormat::Url)],
			[Markdown::class, new MarkdownField('bio')],
			[Select::class, new EnumField('size', ['s', 'm'])],
			[Toggle::class, new BoolField('featured')],
			[Repeater::class, new ListField('tags')],
			[Checkboxes::class, new ListField('days', new EnumField('', ['mon', 'tue']))],
			[Repeater::class, new ListField('steps', new ObjectField('', new Schema([new TextField('text')])))],
			[ViewOnly::class, new ListField('bios', new MarkdownField())],
			[Image::class, new MediaField('cover', MediaKind::Image)],
			[File::class, new MediaField('download')],
			[Group::class, new ObjectField('rights')],
			[Code::class, new SlugField('handle')],
			[Reference::class, new ReferenceField('parent', 'page')],
			[Code::class, new ReferenceField('parent')],
			[ViewOnly::class, new ColorField('accent')]
		];

		foreach ($cases as [$class, $field]) {
			$this->assertInstanceOf($class, $field->editedWith(), $field->name);
		}
	}

	public function testAFieldMayNameAControlThatFitsIt(): void
	{
		$field = new EnumField('size', ['s', 'm'])->control(new Radios());

		$this->assertInstanceOf(Radios::class, $field->editedWith());
		$this->assertSame('radios', $field->toArray()['control'] ?? null);
		$this->assertArrayNotHasKey('control', new EnumField('size', ['s'])->toArray(), 'A field naming none writes none.');
	}

	public function testAFieldRefusesAControlThatDoesNotFit(): void
	{
		$this->expectException(InvalidSchema::class);
		$this->expectExceptionMessage('Field "title" (text) can\'t be edited with the "toggle" control.');

		(void) new TextField('title')->control(new Toggle());
	}

	public function testAControlsOptionsAreWrittenWithIt(): void
	{
		$field = $this->fields->fromArray(['name' => 'source', 'type' => 'text', 'control' => 'text', 'placeholder' => 'Cookbook, site, or person']);

		$this->assertSame(['name' => 'source', 'type' => 'text', 'control' => 'text', 'placeholder' => 'Cookbook, site, or person'], $field->toArray());
		$this->assertEquals($field, $this->fields->fromArray($field->toArray()));

		$steps = $this->fields->fromArray(['name' => 'ingredients', 'control' => 'repeater', 'itemLabel' => 'Ingredient', 'summary' => ['amount', 'name'], 'fields' => [['name' => 'amount', 'type' => 'number'], ['name' => 'name']]]);

		$this->assertInstanceOf(ListField::class, $steps);
		$this->assertInstanceOf(ObjectField::class, $steps->item);
		$this->assertSame('Ingredient', $steps->toArray()['itemLabel'] ?? null);
		$this->assertSame(['amount', 'name'], $steps->toArray()['summary'] ?? null);
		$this->assertEquals($steps, $this->fields->fromArray($steps->toArray()));
	}

	public function testADefinitionMayNameOnlyAControl(): void
	{
		$site  = $this->fields->fromArray(['name' => 'site', 'control' => 'link']);
		$cover = $this->fields->fromArray(['name' => 'cover', 'control' => 'image']);
		$diet  = $this->fields->fromArray(['name' => 'diet', 'control' => 'checkboxes', 'options' => ['vegan', 'vegetarian']]);
		$on    = $this->fields->fromArray(['name' => 'featured', 'control' => 'toggle']);

		$this->assertInstanceOf(TextField::class, $site);
		$this->assertSame(TextFormat::Url, $site->format);
		$this->assertInstanceOf(MediaField::class, $cover);
		$this->assertSame(MediaKind::Image, $cover->kind);
		$this->assertInstanceOf(ListField::class, $diet);
		$this->assertInstanceOf(EnumField::class, $diet->item);
		$this->assertSame(['vegan', 'vegetarian'], $diet->item->options);
		$this->assertInstanceOf(BoolField::class, $on);
		$this->assertSame(['name' => 'site', 'type' => 'text', 'format' => 'url', 'control' => 'link'], $site->toArray(), 'Written with both.');
	}

	public function testAControlFillsInTheOptionsItImpliesOnItsOwnType(): void
	{
		$field = $this->fields->fromArray(['name' => 'site', 'type' => 'text', 'control' => 'link']);

		$this->assertInstanceOf(TextField::class, $field);
		$this->assertSame(TextFormat::Url, $field->format);
	}

	public function testDefinitionsRefuseAControlThatDoesNotFitTheirType(): void
	{
		$cases = [
			['name' => 'title', 'control' => 'wysiwyg'],
			['name' => 'title', 'control' => true],
			['name' => 'count', 'type' => 'number', 'control' => 'toggle'],
			['name' => 'title', 'control' => 'radios'],
			['name' => 'tags', 'type' => 'list', 'control' => 'checkboxes'],
			['name' => 'email', 'type' => 'text', 'format' => 'email', 'control' => 'link']
		];

		foreach ($cases as $definition) {
			try {
				$this->fields->fromArray($definition);
				$this->fail('The definition should be invalid: ' . json_encode($definition));
			} catch (InvalidSchema) {
				$this->addToAssertionCount(1);
			}
		}
	}

	public function testAFieldSwitchesOnlyToControlsThatStoreTheSameValue(): void
	{
		$names = fn (Field $field): array => array_map(static fn (string $class): string => $class::name(), $this->controls->switchesFor($field));

		$this->assertSame(['select', 'radios'], $names(new EnumField('size', ['s'])));
		$this->assertSame(['text', 'textarea'], $names(new TextField('title')));
		$this->assertSame(['link'], $names(new TextField('site', TextFormat::Url)));
		$this->assertSame(['checkboxes', 'multiselect'], $names(new ListField('days', new EnumField('', ['mon']))));
		$this->assertSame(['number'], $names(new NumberField('servings')));
		$this->assertSame([], $names(new SlugField('handle')), 'Code isn\'t offered.');
	}

	public function testFormsGetTheControlAFieldIsEditedWith(): void
	{
		$form = new ColorField('accent')->toForm();

		$this->assertSame('readonly', $form['control'] ?? null);
		$this->assertArrayNotHasKey('class', $form);
		$this->assertSame('code', new ReferenceField('cast')->toForm()['control'] ?? null);
		$this->assertSame('Search for it', new TextField('q')->control(new Text('Search for it'))->toForm()['placeholder'] ?? null);
	}

	public function testFormsGiveNestedFieldsTheirControls(): void
	{
		$group = $this->fields->fromArray(['name' => 'rights', 'control' => 'group', 'fields' => [['name' => 'holder'], ['name' => 'license', 'control' => 'radios', 'type' => 'enum', 'options' => ['cc', 'all']]]])->toForm();
		$steps = $this->fields->fromArray(['name' => 'steps', 'control' => 'repeater', 'fields' => [['name' => 'done', 'type' => 'bool']]])->toForm();

		$this->assertSame('group', $group['control'] ?? null);
		$this->assertSame(['text', 'radios'], array_column(is_array($group['fields'] ?? null) ? $group['fields'] : [], 'control'));
		$this->assertSame('repeater', $steps['control'] ?? null);
		$item = is_array($steps['item'] ?? null) ? $steps['item'] : [];
		$this->assertSame('group', $item['control'] ?? null);
		$this->assertSame(['toggle'], array_column(is_array($item['fields'] ?? null) ? $item['fields'] : [], 'control'));
	}

	public function testThereIsNoReferenceType(): void
	{
		$this->expectException(InvalidSchema::class);
		$this->expectExceptionMessage('entries are linked by relationships');

		$this->fields->fromArray(['name' => 'cast', 'type' => 'reference', 'to' => 'actor']);
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
