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
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\ContentTypeTarget;
use Blush\Content\Type\InvalidContentType;
use Blush\Core\Application;
use Blush\Field\FieldFactory;
use Blush\Field\FieldSetLoader;
use Blush\Field\FieldSetOrigin;
use Blush\Tests\BootsScratchSite;
use Blush\Tests\Fixtures\Field\SeoProvider;

#[CoversClass(FieldSetLoader::class)]
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
		$this->assertSame(FieldSetOrigin::Data, $types->sets->origin('kitchen'));
		$this->assertFalse($types->schema('page')->has('cook_time'), 'Only the types a set targets get its fields.');
	}

	public function testSetsAreAttachedInNameOrder(): void
	{
		$this->recipes();
		$this->writeTemporaryFile('user/data/fields/zest.json', '{"targets": "type:recipe", "fields": [{"name": "zest"}]}');
		$this->writeTemporaryFile('user/data/fields/apron.json', '{"targets": ["type:recipe"], "fields": [{"name": "apron"}]}');

		$this->assertSame(['servings', 'apron', 'zest'], array_slice(array_keys($this->types()->schema('recipe')->fields), -3));
	}

	public function testASetCantReuseAFieldsName(): void
	{
		$this->recipes();
		$this->writeTemporaryFile('user/data/fields/kitchen.json', '{"targets": ["type:recipe"], "fields": [{"name": "servings"}]}');

		$this->expectException(InvalidContentType::class);
		$this->expectExceptionMessage('type:recipe can\'t take field set "kitchen": Schema key "servings"');

		$this->types();
	}

	public function testASetCantReuseABuiltInFieldsAlias(): void
	{
		$this->writeTemporaryFile('user/data/fields/dates.json', '{"targets": ["type:page"], "fields": [{"name": "date"}]}');

		$this->expectException(InvalidContentType::class);
		$this->expectExceptionMessage('can\'t take field set "dates"');

		$this->types();
	}

	public function testExtensionsConfigAndDataSetsReplaceInTurn(): void
	{
		$application = $this->scratchApplication();
		$application->register(SeoProvider::class);

		$types = $this->types($application);

		$this->assertSame(FieldSetOrigin::Extension, $types->sets->origin('seo'));
		$this->assertTrue($types->schema('page')->has('noindex'));

		$this->writeTemporaryFile('config/fields.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn Blush\\Field\\FieldConfig::fromArray(['sets' => ['seo' => ['targets' => ['type:page'], 'fields' => ['meta_title' => []]]]]);\n");

		$application = $this->scratchApplication();
		$application->register(SeoProvider::class);
		$types = $this->types($application);

		$this->assertSame(FieldSetOrigin::Config, $types->sets->origin('seo'));
		$this->assertFalse($types->schema('page')->has('noindex'), 'The config\'s set replaces the extension\'s whole.');

		$this->writeTemporaryFile('user/data/fields/seo.json', '{"targets": ["type:page"], "fields": [{"name": "canonical"}]}');

		$types = $this->types();

		$this->assertSame(FieldSetOrigin::Data, $types->sets->origin('seo'));
		$this->assertTrue($types->schema('page')->has('canonical'));
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
		$this->expectExceptionMessage('user/data/fields/seo names the field set "search"; a data set is named after its file.');

		$this->types();
	}

	public function testAnInvalidSetNamesItsFile(): void
	{
		$this->writeTemporaryFile('user/data/fields/seo.json', '{"targets": ["type:page"], "fields": [{"name": "x", "type": "nope"}]}');

		$this->expectException(InvalidContentType::class);
		$this->expectExceptionMessage('user/data/fields/seo: Field set "seo": Field "x" has an unknown type "nope".');

		$this->types();
	}

	public function testATargetThatIsntThereIsLeftAlone(): void
	{
		$this->writeTemporaryFile('user/data/fields/shop.json', '{"targets": ["type:product"], "fields": [{"name": "price", "type": "number"}]}');

		$types = $this->types();

		$this->assertCount(1, $types->sets);
		$this->assertFalse($types->schema('page')->has('price'));
	}

	public function testSetsAreCompiledWithTheTypes(): void
	{
		$this->recipes();
		$this->writeTemporaryFile('user/data/fields/kitchen.json', '{"targets": ["type:recipe"], "fields": [{"name": "oven", "type": "enum", "options": ["gas", "electric"], "control": "radios"}]}');

		$application = $this->scratchApplication();
		$types       = $this->types($application);
		$compiled    = ContentTypes::fromArray($types->toArray(), $application->container()->make(FieldFactory::class));

		$this->assertEquals($types->sets, $compiled->sets);
		$this->assertEquals($types->schema('recipe'), $compiled->schema('recipe'));
	}
}
