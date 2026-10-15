<?php

/**
 * Type layout tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Content\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Config\InvalidConfig;
use Blush\Content\EntryFields;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\ContentTypeTarget;
use Blush\Content\Type\InvalidContentType;
use Blush\Core\Application;
use Blush\Field\Controls\Radios;
use Blush\Field\FieldFactory;
use Blush\Field\FieldLibrary;
use Blush\Field\FieldLibraryLoader;
use Blush\Field\Fields\TextField;
use Blush\Field\InvalidSchema;
use Blush\Field\RegisteredField;
use Blush\Tests\BootsScratchSite;
use Blush\Tests\Fixtures\Field\SeoProvider;
use Blush\Tests\WritesContentConfig;
use Blush\Translation\Messages;

#[CoversClass(FieldLibraryLoader::class)]
#[CoversClass(FieldLibrary::class)]
#[CoversClass(EntryFields::class)]
#[CoversClass(RegisteredField::class)]
#[CoversClass(ContentTypeTarget::class)]
#[CoversClass(ContentTypes::class)]
final class TypeLayoutTest extends TestCase
{
	use BootsScratchSite;
	use WritesContentConfig;

	/**
	 * Gives the site a `recipe` collection placing library fields, and the
	 * library's `cook_time` and `oven`.
	 */
	private function recipes(string $layout = '["cook_time", "oven"]'): void
	{
		$this->writeTemporaryFile('user/data/types/recipe.json', "{\"fields\": {$layout}}");
		$this->writeTemporaryFile('user/data/fields/cook_time.json', '{"type": "number", "label": "Cook time"}');
		$this->writeTemporaryFile('user/data/fields/oven.json', '{"type": "enum", "options": ["gas", "electric"], "control": {"name": "radios"}}');
	}

	private function types(?Application $application = null): ContentTypes
	{
		return ($application ?? $this->scratchApplication())->container()->make(ContentTypes::class);
	}

	public function testALayoutsFieldsComeAfterTheTypesOwn(): void
	{
		$this->recipes('["oven", {"section": "Timing", "fields": ["cook_time"]}]');

		$types = $this->types();

		$this->assertSame(['oven', 'cook_time'], array_slice(array_keys($types->schema('recipe')->fields), -2), 'In the layout\'s order.');
		$this->assertSame(['oven', 'cook_time'], array_keys($types->carried('recipe')));
		$this->assertFalse($types->schema('page')->has('cook_time'), 'Only the types that place a field have it (D-858).');
		$this->assertInstanceOf(Radios::class, $types->controls('recipe')->at('oven'), 'Drawn with the library field\'s control.');
		$this->assertNull($types->controls('page')->at('oven'));
	}

	public function testATypeChangesOnlyHowAFieldIsAskedFor(): void
	{
		$this->recipes('[{"field": "cook_time", "label": "Time on the stove", "description": "Minutes.", "required": true}]');
		$this->writeTemporaryFile('user/data/types/page.json', '{"fields": ["cook_time"]}');

		$types  = $this->types();
		$recipe = $types->schema('recipe')->field('cook_time');
		$page   = $types->schema('page')->field('cook_time');

		$this->assertSame(['Time on the stove', 'Minutes.', true, 'number'], [$recipe?->label, $recipe?->description, $recipe?->required, $recipe?->type()]);
		$this->assertSame(['Cook time', '', false, 'number'], [$page?->label, $page?->description, $page?->required, $page?->type()], 'The field\'s own on another type.');
	}

	public function testAFieldThatReusesATypesKeyIsLeftOffIt(): void
	{
		$this->codeConfig(['types' => ['recipe' => ['fields' => [['name' => 'servings', 'type' => 'number']]]]]);
		$this->writeTemporaryFile('user/data/types/recipe.json', '{"fields": ["servings"]}');
		$this->writeTemporaryFile('user/data/types/page.json', '{"fields": ["servings"]}');
		$this->writeTemporaryFile('user/data/fields/servings.json', '{"type": "text"}');

		$types = $this->types();

		$this->assertSame('number', $types->schema('recipe')->field('servings')?->type(), 'The type\'s own field is kept, and the site still loads (D-786).');
		$this->assertSame('text', $types->schema('page')->field('servings')?->type(), 'It\'s still on the types it fits.');
		$this->assertSame(
			['servings' => ['type:recipe can\'t take the "servings" field: "servings" is already one of its own fields.']],
			self::clashes($types, 'recipe')
		);
	}

	public function testNoLibraryFieldDeclaresCoresKeys(): void
	{
		$this->writeTemporaryFile('user/data/types/page.json', '{"fields": ["date", "when"]}');
		$this->writeTemporaryFile('user/data/fields/date.json', '{"type": "text"}');
		$this->writeTemporaryFile('user/data/fields/when.json', '{"type": "text"}');

		$types = $this->types();

		$this->assertTrue($types->schema('page')->has('when'), 'Only the field that clashes is left off.');
		$this->assertSame(
			['date' => ['type:page can\'t take the "date" field: "date" is Blush\'s own key, so nothing else can declare it.']],
			self::clashes($types, 'page')
		);
	}

	public function testCoreRegistersTheEntryFieldsEveryTypeStartsWith(): void
	{
		$types = $this->types();
		$core  = array_keys(EntryFields::schema(new Messages())->fields);

		$this->assertSame($core, array_slice(array_keys($types->schema('page')->fields), 0, count($core)), 'A type\'s fields start with them (D-790).');
		$this->assertSame($core, array_map(static fn (RegisteredField $field): string => $field->field->name, $types->library->registered->fields), 'Registered by core, never in the library.');
		$this->assertSame([], $types->library->all());
	}

	public function testExtensionsRegisterFieldsOutsideTheLibrary(): void
	{
		$application = $this->scratchApplication();
		$application->register(SeoProvider::class);

		$types = $this->types($application);

		$this->assertTrue($types->schema('page')->has('meta_title'), 'Declared, so it\'s data the type has.');
		$this->assertTrue($types->schema('page')->has('noindex'));
		$this->assertSame([], $types->library->all(), 'Not the library\'s, so the admin shows nothing (D-791).');
		$this->assertSame([], $types->carried('page'));
		$this->assertFalse($types->formSchema('page')->has('meta_title'), 'Not drawn in the editor (D-791).');
		$this->assertFalse($types->formSchema('page')->has('noindex'));
		$this->assertTrue($types->formSchema('page')->has('title'));

		$compiled = ContentTypes::fromArray($types->toArray(), $application->container()->make(FieldFactory::class), $application->container()->make(Messages::class));

		$this->assertEquals($types->schema('page'), $compiled->schema('page'), 'Kept with the compiled types.');
	}

	public function testALibraryFieldIsLeftOffWhereAnExtensionRegisteredItsKey(): void
	{
		$this->writeTemporaryFile('user/data/types/page.json', '{"fields": ["meta_title"]}');
		$this->writeTemporaryFile('user/data/fields/meta_title.json', '{"type": "text"}');

		$application = $this->scratchApplication();
		$application->register(SeoProvider::class);

		$this->assertSame(
			['meta_title' => ['type:page can\'t take the "meta_title" field: acme/seo\'s field "meta_title" declares "meta_title" there already, and a key is declared once.']],
			self::clashes($this->types($application), 'page'),
			'Code first, so the site renames its own (D-789).'
		);
	}

	public function testAnExtensionCantRegisterAsCore(): void
	{
		$this->expectException(InvalidSchema::class);
		$this->expectExceptionMessage('Field "x" is registered by "acme"; name the extension registering it, such as "acme/seo".');

		new RegisteredField('acme', new TextField('x'), ['type:page']);
	}

	public function testADataTypeDefinesNoFields(): void
	{
		$this->writeTemporaryFile('user/data/types/recipe.json', '{"fields": [{"name": "servings", "type": "number"}]}');

		$this->expectException(InvalidContentType::class);
		$this->expectExceptionMessage('its "fields" list library fields by key; a field is defined in the field library');

		$this->types();
	}

	public function testADataTypeCantSetCodesOwnFields(): void
	{
		$this->writeTemporaryFile('user/data/types/recipe.json', '{"ownFields": [{"name": "servings", "type": "number"}]}');

		$this->expectException(InvalidContentType::class);
		$this->expectExceptionMessage('user/data/types/recipe sets "ownFields", which only code writes');

		$this->types();
	}

	public function testConfigDoesntMakeFields(): void
	{
		$this->writeTemporaryFile('config/fields.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn Blush\\Field\\FieldConfig::fromArray(['sets' => ['seo' => []]]);\n");

		$this->expectException(InvalidConfig::class);

		$this->types();
	}

	public function testTheLibraryCanBeTurnedOff(): void
	{
		$this->writeTemporaryFile('config/fields.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Field\\FieldConfig(library: false);\n");
		$this->recipes();

		$types = $this->types();

		$this->assertSame([], $types->library->all());
		$this->assertFalse($types->schema('recipe')->has('oven'), 'A name the library doesn\'t have is left out.');
	}

	public function testALibraryFieldIsNamedAfterItsFile(): void
	{
		$this->writeTemporaryFile('user/data/fields/seo.json', '{"name": "search", "type": "text"}');

		$this->expectException(InvalidContentType::class);
		$this->expectExceptionMessage('user/data/fields/seo.json names the record "search"; a record here is named after its file.');

		$this->types();
	}

	public function testAnInvalidFieldNamesItsFile(): void
	{
		$this->writeTemporaryFile('user/data/fields/seo.json', '{"type": "nope"}');

		try {
			$this->types();
			$this->fail('The field should be invalid.');
		} catch (InvalidContentType $e) {
			$this->assertStringStartsWith('user/data/fields/seo.json: Field "seo"', $e->getMessage());
			$this->assertStringContainsString('unknown type "nope"', $e->getMessage());
		}
	}

	public function testTheLibraryIsCompiledWithTheTypes(): void
	{
		$this->recipes();

		$application = $this->scratchApplication();
		$types       = $this->types($application);
		$compiled    = ContentTypes::fromArray($types->toArray(), $application->container()->make(FieldFactory::class), $application->container()->make(Messages::class));

		$this->assertEquals($types->library, $compiled->library);
		$this->assertEquals($types->get('recipe')->layout, $compiled->get('recipe')->layout);
		$this->assertEquals($types->schema('recipe'), $compiled->schema('recipe'));
	}

	/**
	 * Returns why library fields are left off a type, as text.
	 *
	 * @return array<string, list<string>>
	 */
	private static function clashes(ContentTypes $types, string $type): array
	{
		return array_map(
			static fn (array $messages): array => array_map(strval(...), $messages),
			$types->library->clashes(new ContentTypeTarget($types, $types->get($type)), new Messages())
		);
	}
}
