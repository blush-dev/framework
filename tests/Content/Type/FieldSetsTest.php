<?php

/**
 * Content type field set tests.
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
use Blush\Field\FieldFactory;
use Blush\Field\FieldSet;
use Blush\Field\FieldSetLoader;
use Blush\Field\Fields\TextField;
use Blush\Field\InvalidSchema;
use Blush\Field\RegisteredField;
use Blush\Tests\BootsScratchSite;
use Blush\Tests\Fixtures\Field\SeoProvider;

#[CoversClass(FieldSetLoader::class)]
#[CoversClass(EntryFields::class)]
#[CoversClass(RegisteredField::class)]
#[CoversClass(ContentTypeTarget::class)]
#[CoversClass(ContentTypes::class)]
final class FieldSetsTest extends TestCase
{
	use BootsScratchSite;

	/**
	 * Gives the site a `recipe` collection with a field of its own.
	 */
	private function recipes(): void
	{
		$this->writeTemporaryFile('user/data/types/recipe.json', '{"fields": [{"name": "servings", "type": "number"}]}');
	}

	private function types(?Application $application = null): ContentTypes
	{
		return ($application ?? $this->scratchApplication())->container()->make(ContentTypes::class);
	}

	public function testASetsFieldsComeAfterTheTypesOwn(): void
	{
		$this->recipes();
		$this->writeTemporaryFile('user/data/fields/kitchen.json', '{"label": "In the Kitchen", "targets": ["type:recipe"], "fields": {"cook_time": {"type": "number"}, "oven": {"type": "enum", "options": ["gas", "electric"]}}}');

		$types  = $this->types();
		$schema = $types->schema('recipe');
		$names  = array_keys($schema->fields);

		$this->assertSame(['servings', 'cook_time', 'oven'], array_slice($names, -3));
		$this->assertSame(['kitchen'], array_column($types->setsFor('recipe'), 'name'));
		$this->assertSame('In the Kitchen', $types->setsFor('recipe')[0]->label);
		$this->assertFalse($types->schema('page')->has('cook_time'), 'Only the types a set targets get its fields.');
	}

	public function testSetsAreAttachedInNameOrder(): void
	{
		$this->recipes();
		$this->writeTemporaryFile('user/data/fields/zest.json', '{"targets": "type:recipe", "fields": [{"name": "zest"}]}');
		$this->writeTemporaryFile('user/data/fields/apron.json', '{"targets": ["type:recipe"], "fields": [{"name": "apron"}]}');

		$this->assertSame(['servings', 'apron', 'zest'], array_slice(array_keys($this->types()->schema('recipe')->fields), -3));
	}

	public function testATypesSetsGoByPositionThenLabel(): void
	{
		$this->recipes();
		$this->writeTemporaryFile('user/data/fields/zest.json', '{"label": "Zest", "targets": ["type:recipe"], "position": 1, "fields": [{"name": "zest"}]}');
		$this->writeTemporaryFile('user/data/fields/apron.json', '{"label": "Apron", "targets": ["type:recipe"], "fields": [{"name": "apron"}]}');
		$this->writeTemporaryFile('user/data/fields/basil.json', '{"label": "Basil", "targets": ["type:recipe"], "fields": [{"name": "basil"}]}');

		$this->assertSame(['zest', 'apron', 'basil'], array_map(static fn (FieldSet $set): string => $set->name, $this->types()->setsFor('recipe')), 'The editor\'s order (D-768).');
	}

	public function testASetThatReusesATypesKeyIsLeftOffIt(): void
	{
		$this->recipes();
		$this->writeTemporaryFile('user/data/fields/kitchen.json', '{"targets": ["type:recipe", "type:page"], "fields": [{"name": "servings"}]}');

		$types = $this->types();

		$this->assertSame('number', $types->schema('recipe')->field('servings')?->type(), 'The type\'s own field is kept, and the site still loads (D-786).');
		$this->assertTrue($types->schema('page')->has('servings'), 'It\'s still on the types it fits.');
		$this->assertSame(
			['kitchen' => ['type:recipe can\'t take field set "kitchen": "servings" is already one of its own fields.']],
			$types->sets->clashes(new ContentTypeTarget($types, $types->get('recipe')))
		);
	}

	public function testNoSetDeclaresCoresKeys(): void
	{
		$this->writeTemporaryFile('user/data/fields/dates.json', '{"targets": ["type:page"], "fields": [{"name": "date"}, {"name": "when"}]}');

		$types = $this->types();

		$this->assertFalse($types->schema('page')->has('when'), 'The whole set is left off.');
		$this->assertSame(
			['dates' => ['type:page can\'t take field set "dates": "date" is Blush\'s own key, so nothing else can declare it.']],
			$types->sets->clashes(new ContentTypeTarget($types, $types->get('page')))
		);
	}

	public function testAKeyIsDeclaredOnceAcrossSets(): void
	{
		$this->writeTemporaryFile('user/data/fields/apron.json', '{"targets": ["type:page"], "fields": [{"name": "note"}]}');
		$this->writeTemporaryFile('user/data/fields/zest.json', '{"targets": ["type:*"], "fields": [{"name": "zest"}, {"name": "note", "type": "markdown"}]}');

		$types = $this->types();

		$this->assertSame('text', $types->schema('page')->field('note')?->type(), 'The first set, by name, keeps it.');
		$this->assertFalse($types->schema('page')->has('zest'));
		$this->assertSame(
			['zest' => ['type:page can\'t take field set "zest": field set "apron" declares "note" there already, and a key is declared once.']],
			$types->sets->clashes(new ContentTypeTarget($types, $types->get('page')))
		);
	}

	public function testCoreRegistersTheEntryFieldsEveryTypeStartsWith(): void
	{
		$types = $this->types();
		$core  = array_keys(EntryFields::schema()->fields);

		$this->assertSame($core, array_slice(array_keys($types->schema('page')->fields), 0, count($core)), 'A type\'s fields start with them (D-790).');
		$this->assertSame($core, array_map(static fn (RegisteredField $field): string => $field->field->name, $types->sets->registered->fields), 'Registered by core, never a set.');
		$this->assertSame([], $types->sets->all());
	}

	public function testASetCanBeOnEveryType(): void
	{
		$this->recipes();
		$this->writeTemporaryFile('user/data/fields/notes.json', '{"targets": ["type:*"], "fields": [{"name": "note"}]}');

		$types = $this->types();

		$this->assertTrue($types->schema('recipe')->has('note'));
		$this->assertTrue($types->schema('page')->has('note'));
		$this->assertSame(['notes'], array_column($types->setsFor('page'), 'name'));
	}

	public function testExtensionsRegisterFieldsThatArentSets(): void
	{
		$application = $this->scratchApplication();
		$application->register(SeoProvider::class);

		$types = $this->types($application);

		$this->assertTrue($types->schema('page')->has('meta_title'), 'Declared, so it\'s data the type has.');
		$this->assertTrue($types->schema('page')->has('noindex'));
		$this->assertSame([], $types->sets->all(), 'Not a set, so the admin shows nothing (D-791).');
		$this->assertSame([], $types->setsFor('page'));
		$this->assertFalse($types->formSchema('page')->has('meta_title'), 'Not drawn in the editor (D-791).');
		$this->assertFalse($types->formSchema('page')->has('noindex'));
		$this->assertTrue($types->formSchema('page')->has('title'));

		$compiled = ContentTypes::fromArray($types->toArray(), $application->container()->make(FieldFactory::class));

		$this->assertEquals($types->schema('page'), $compiled->schema('page'), 'Kept with the compiled types.');
	}

	public function testASetIsLeftOffWhereAnExtensionRegisteredItsKey(): void
	{
		$this->writeTemporaryFile('user/data/fields/search.json', '{"targets": ["type:page"], "fields": [{"name": "meta_title"}]}');

		$application = $this->scratchApplication();
		$application->register(SeoProvider::class);

		$types = $this->types($application);

		$this->assertSame(
			['search' => ['type:page can\'t take field set "search": acme/seo\'s field "meta_title" declares "meta_title" there already, and a key is declared once.']],
			$types->sets->clashes(new ContentTypeTarget($types, $types->get('page'))),
			'Code first, so the site renames its own (D-789).'
		);
	}

	public function testAnExtensionCantRegisterAsCore(): void
	{
		$this->expectException(InvalidSchema::class);
		$this->expectExceptionMessage('Field "x" is registered by "acme"; name the extension registering it, such as "acme/seo".');

		new RegisteredField('acme', new TextField('x'), ['type:page']);
	}

	public function testConfigDoesntMakeFields(): void
	{
		$this->writeTemporaryFile('config/fields.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn Blush\\Field\\FieldConfig::fromArray(['sets' => ['seo' => []]]);\n");

		$this->expectException(InvalidConfig::class);

		$this->types();
	}

	public function testDataSetsCanBeTurnedOff(): void
	{
		$this->writeTemporaryFile('config/fields.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn new Blush\\Field\\FieldConfig(dataSets: false);\n");
		$this->writeTemporaryFile('user/data/fields/seo.json', '{"targets": ["type:page"], "fields": [{"name": "canonical"}]}');

		$this->assertSame([], $this->types()->sets->all());
	}

	public function testADataSetIsNamedAfterItsFile(): void
	{
		$this->writeTemporaryFile('user/data/fields/seo.json', '{"name": "search", "targets": ["type:page"]}');

		$this->expectException(InvalidContentType::class);
		$this->expectExceptionMessage('user/data/fields/seo.json names the record "search"; a record here is named after its file.');

		$this->types();
	}

	public function testAnInvalidSetNamesItsFile(): void
	{
		$this->writeTemporaryFile('user/data/fields/seo.json', '{"targets": ["type:page"], "fields": [{"name": "x", "type": "nope"}]}');

		$this->expectException(InvalidContentType::class);
		$this->expectExceptionMessage('user/data/fields/seo.json: Field set "seo": Field "x" has an unknown type "nope".');

		$this->types();
	}

	public function testATargetThatIsntThereIsLeftAlone(): void
	{
		$this->writeTemporaryFile('user/data/fields/shop.json', '{"targets": ["type:product"], "fields": [{"name": "price", "type": "number"}]}');

		$types = $this->types();

		$this->assertNotNull($types->sets->find('shop'));
		$this->assertFalse($types->schema('page')->has('price'));
	}

	public function testSetsAreCompiledWithTheTypes(): void
	{
		$this->recipes();
		$this->writeTemporaryFile('user/data/fields/kitchen.json', '{"targets": ["type:recipe"], "fields": [{"name": "oven", "type": "enum", "options": ["gas", "electric"], "control": {"name": "radios"}}]}');

		$application = $this->scratchApplication();
		$types       = $this->types($application);
		$compiled    = ContentTypes::fromArray($types->toArray(), $application->container()->make(FieldFactory::class));

		$this->assertEquals($types->sets, $compiled->sets);
		$this->assertEquals($types->schema('recipe'), $compiled->schema('recipe'));
	}
}
