<?php

/**
 * Form field tests.
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
use Blush\Content\EntryFields;
use Blush\Field\ControlDefaults;
use Blush\Field\Controls\Code;
use Blush\Field\Controls\Radios;
use Blush\Field\Controls\Textarea;
use Blush\Field\Controls\ViewOnly;
use Blush\Field\FieldControls;
use Blush\Field\Fields\ListField;
use Blush\Field\Fields\ObjectField;
use Blush\Field\Fields\TextField;
use Blush\Field\FormField;
use Blush\Field\Schema;
use Blush\Tests\Fixtures\Field\RatingField;
use Blush\Translation\Messages;

#[CoversClass(FormField::class)]
#[CoversClass(FieldControls::class)]
#[CoversClass(ControlDefaults::class)]
final class FormFieldTest extends TestCase
{
	public function testDrawsCoresEntryFieldsWithTheirControls(): void
	{
		$forms = array_map(static fn (FormField $field): array => $field->toArray(), FormField::all(EntryFields::schema(new Messages()), EntryFields::controls(), new ControlDefaults()));

		$this->assertSame(
			['text', 'text', 'text', 'date', 'date', 'date', 'text', 'date', 'select', 'markdown', 'image', 'text', 'text', 'text', 'reference', 'group'],
			array_column($forms, 'control')
		);
		$this->assertSame(['public' => 'public', 'unlisted' => 'unlisted', 'hidden' => 'hidden'], array_column($forms, 'choices', 'name')['visibility'] ?? null, 'A choice\'s option labels.');
		$this->assertArrayNotHasKey('class', $forms[0]);
	}

	public function testDrawsEachPathWithItsChosenControl(): void
	{
		$field    = new ObjectField('details', new Schema([new TextField('oven'), new ListField('notes', new TextField())]));
		$controls = new FieldControls(['details.oven' => new Textarea(), 'details.notes.*' => new Code()]);
		$form     = FormField::of($field, $controls, new ControlDefaults());

		$this->assertInstanceOf(Textarea::class, $form->fields[0]->control ?? null);
		$this->assertInstanceOf(Code::class, $form->fields[1]->item->control ?? null, 'A list\'s item, by `*`.');
		$this->assertSame('group', $form->toArray()['control'] ?? null, 'Its default.');
	}

	public function testAnotherFieldTypesDefaultIsRegistered(): void
	{
		$defaults = new ControlDefaults();
		$rating   = new RatingField('stars');

		$this->assertInstanceOf(ViewOnly::class, $defaults->for($rating), 'Read-only until one is named.');

		$defaults->register('rating', Radios::class);

		$this->assertInstanceOf(Radios::class, $defaults->for($rating));
	}
}
