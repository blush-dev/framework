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
use Blush\Field\ControlDefaults;
use Blush\Field\ControlType;
use Blush\Field\Field;
use Blush\Field\FieldControls;
use Blush\Field\FieldFactory;
use Blush\Field\FieldRegistrar;
use Blush\Field\FieldRegistry;
use Blush\Field\Fields\BoolField;
use Blush\Field\Fields\EnumField;
use Blush\Field\Fields\ListField;
use Blush\Field\Fields\MarkdownField;
use Blush\Field\Fields\MediaField;
use Blush\Field\Fields\ObjectField;
use Blush\Field\Fields\ReferenceField;
use Blush\Field\Fields\SlugField;
use Blush\Field\Fields\TextField;
use Blush\Field\InvalidSchema;
use Blush\Field\FormField;
use Blush\Field\Schema;
use Blush\Field\TextFormat;
use Blush\Media\MediaKind;
use Blush\Tests\Fixtures\Content\ColorField;
use Blush\Translation\Messages;

#[CoversClass(Control::class)]
#[CoversClass(ControlFactory::class)]
#[CoversClass(ControlRegistrar::class)]
#[CoversClass(ControlRegistry::class)]
#[CoversClass(ChoiceList::class)]
#[CoversClass(Repeater::class)]
#[CoversClass(TextBox::class)]
#[CoversClass(Field::class)]
#[CoversClass(FieldFactory::class)]
#[CoversClass(ControlDefaults::class)]
#[CoversClass(FormField::class)]
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
			$this->assertNotSame('', (string) $class::label(new Messages()), $type->value);
			$this->assertNotSame('', (string) $class::description(new Messages()), $type->value);

			if ($class::section() !== null) {
				$this->assertNotNull($class::type(), "{$type->value} is offered, so it names the type it stores.");
			}
		}

		$this->assertSame(array_column(ControlType::cases(), 'value'), array_keys($this->controls->all()));
	}

	public function testAFieldIsDrawnWithItsTypesDefaultControl(): void
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
			$this->assertInstanceOf($class, new ControlDefaults()->for($field), $field->name);
		}
	}

	public function testAFieldHoldsNoControl(): void
	{
		$this->assertArrayNotHasKey('control', new EnumField('size', ['s'])->toArray());

		$this->expectException(InvalidSchema::class);
		$this->expectExceptionMessage('Field "size" names a control, but a field holds none; a form chooses it (D-795).');

		$this->fields->fromArray(['name' => 'size', 'type' => 'enum', 'control' => 'radios']);
	}

	public function testADefinitionChoosesAControlThatFitsIt(): void
	{
		[[$field], $controls] = $this->fields->withControls([['name' => 'size', 'type' => 'enum', 'options' => ['s', 'm'], 'control' => ['name' => 'radios']]]);

		$this->assertInstanceOf(Radios::class, $controls->at('size'));
		$this->assertSame([['name' => 'size', 'type' => 'enum', 'options' => ['s', 'm'], 'control' => ['name' => 'radios']]], $controls->definitions([$field]));
	}

	public function testADefinitionRefusesAControlThatDoesNotFit(): void
	{
		$this->expectException(InvalidSchema::class);
		$this->expectExceptionMessage('Field "title" (text) can\'t be edited with the "toggle" control.');

		$this->fields->withControls([['name' => 'title', 'type' => 'text', 'control' => ['name' => 'toggle']]]);
	}

	public function testAControlsOptionsAreWrittenWithIt(): void
	{
		$source = ['name' => 'source', 'type' => 'text', 'control' => ['name' => 'text', 'placeholder' => 'Cookbook, site, or person']];

		[$fields, $controls] = $this->fields->withControls([$source]);

		$this->assertSame([$source], $controls->definitions($fields));

		[$steps, $controls] = $this->fields->withControls([['name' => 'ingredients', 'control' => ['name' => 'repeater', 'itemLabel' => 'Ingredient', 'summary' => ['amount', 'name']], 'fields' => [['name' => 'amount', 'type' => 'number'], ['name' => 'name']]]]);

		$this->assertInstanceOf(ListField::class, $steps[0]);
		$this->assertInstanceOf(ObjectField::class, $steps[0]->item);
		$this->assertSame(['itemLabel' => 'Ingredient', 'summary' => ['amount', 'name']], $controls->at('ingredients')?->options());
	}

	public function testADefinitionMayNameOnlyAControl(): void
	{
		[[$site, $cover, $diet, $on], $controls] = $this->fields->withControls([
			['name' => 'site', 'control' => ['name' => 'link']],
			['name' => 'cover', 'control' => ['name' => 'image']],
			['name' => 'diet', 'control' => ['name' => 'checkboxes'], 'options' => ['vegan', 'vegetarian']],
			['name' => 'featured', 'control' => ['name' => 'toggle']]
		]);

		$this->assertInstanceOf(TextField::class, $site);
		$this->assertSame(TextFormat::Url, $site->format);
		$this->assertInstanceOf(MediaField::class, $cover);
		$this->assertSame(MediaKind::Image, $cover->kind);
		$this->assertInstanceOf(ListField::class, $diet);
		$this->assertInstanceOf(EnumField::class, $diet->item);
		$this->assertSame(['vegan', 'vegetarian'], $diet->item->options);
		$this->assertInstanceOf(BoolField::class, $on);
		$this->assertSame([['name' => 'site', 'type' => 'text', 'format' => 'url', 'control' => ['name' => 'link']]], $controls->definitions([$site]), 'Written with both.');
	}

	public function testAControlFillsInTheOptionsItImpliesOnItsOwnType(): void
	{
		[[$field]] = $this->fields->withControls([['name' => 'site', 'type' => 'text', 'control' => ['name' => 'link']]]);

		$this->assertInstanceOf(TextField::class, $field);
		$this->assertSame(TextFormat::Url, $field->format);
	}

	public function testDefinitionsRefuseAControlThatDoesNotFitTheirType(): void
	{
		$cases = [
			['name' => 'title', 'control' => ['name' => 'wysiwyg']],
			['name' => 'title', 'control' => true],
			['name' => 'title', 'control' => 'text'],
			['name' => 'count', 'type' => 'number', 'control' => ['name' => 'toggle']],
			['name' => 'title', 'control' => ['name' => 'radios']],
			['name' => 'tags', 'type' => 'list', 'control' => ['name' => 'checkboxes']],
			['name' => 'email', 'type' => 'text', 'format' => 'email', 'control' => ['name' => 'link']]
		];

		foreach ($cases as $definition) {
			try {
				$this->fields->withControls([$definition]);
				$this->fail('The definition should be invalid: ' . json_encode($definition));
			} catch (InvalidSchema) {
				$this->addToAssertionCount(1);
			}
		}
	}

	public function testFormsGetTheControlAFieldIsDrawnWith(): void
	{
		$defaults = new ControlDefaults();
		$form     = FormField::of(new ColorField('accent'), new FieldControls(), $defaults)->toArray();

		$this->assertSame('readonly', $form['control'] ?? null);
		$this->assertArrayNotHasKey('class', $form);
		$this->assertSame('code', FormField::of(new ReferenceField('cast'), new FieldControls(), $defaults)->toArray()['control'] ?? null);
		$this->assertSame('Search for it', FormField::of(new TextField('q'), new FieldControls(['q' => new Text('Search for it')]), $defaults)->toArray()['placeholder'] ?? null);
	}

	public function testFormsGiveNestedFieldsTheirControls(): void
	{
		$defaults = new ControlDefaults();

		[[$rights], $controls] = $this->fields->withControls([['name' => 'rights', 'control' => ['name' => 'group'], 'fields' => [['name' => 'holder'], ['name' => 'license', 'control' => ['name' => 'radios'], 'type' => 'enum', 'options' => ['cc', 'all']]]]]);
		$group                 = FormField::of($rights, $controls, $defaults)->toArray();

		[[$list], $controls] = $this->fields->withControls([['name' => 'steps', 'control' => ['name' => 'repeater'], 'fields' => [['name' => 'done', 'type' => 'bool']]]]);
		$steps               = FormField::of($list, $controls, $defaults)->toArray();

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
