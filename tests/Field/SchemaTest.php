<?php

/**
 * Schema tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Field;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Field\FieldContext;
use Blush\Field\FieldFactory;
use Blush\Field\FieldRegistrar;
use Blush\Field\FieldRegistry;
use Blush\Field\Fields\DateField;
use Blush\Field\Fields\EnumField;
use Blush\Field\Fields\ListField;
use Blush\Field\Fields\ObjectField;
use Blush\Field\Fields\ReferenceField;
use Blush\Field\Fields\TextField;
use Blush\Field\FieldType;
use Blush\Field\InvalidSchema;
use Blush\Field\Schema;
use Blush\Field\SchemaResult;
use Blush\Field\Severity;
use Blush\Field\Violation;
use Blush\Tests\Fixtures\Content\ColorField;

#[CoversClass(Schema::class)]
#[CoversClass(SchemaResult::class)]
#[CoversClass(Violation::class)]
#[CoversClass(Severity::class)]
#[CoversClass(FieldFactory::class)]
#[CoversClass(FieldRegistry::class)]
#[CoversClass(FieldRegistrar::class)]
#[CoversClass(FieldType::class)]
#[CoversClass(InvalidSchema::class)]
final class SchemaTest extends TestCase
{
	private FieldContext $context;

	private FieldFactory $factory;

	protected function setUp(): void
	{
		$this->context = new FieldContext(new DateTimeZone('America/Chicago'));

		$registry = new FieldRegistry();
		new FieldRegistrar($registry)->register();
		$this->factory = new FieldFactory($registry);
	}

	private function schema(bool $closed = false): Schema
	{
		return new Schema([
			new TextField('title')->required(),
			new DateField('published')->aliases('date'),
			new EnumField('status', ['published', 'draft'])->default('published'),
			new ReferenceField('authors', 'author')->aliases('author'),
			new ListField('template')->aliases('view')
		], $closed);
	}

	/**
	 * @param  list<Violation> $violations
	 * @return list<string>
	 */
	private static function messages(array $violations): array
	{
		return array_map(static fn (Violation $violation): string => "{$violation->severity->value} {$violation}", $violations);
	}

	public function testMediaFieldsMayNameTheirKind(): void
	{
		$field = $this->factory->fromArray(['name' => 'poster', 'type' => 'media', 'kind' => 'image']);

		$this->assertSame(['name' => 'poster', 'type' => 'media', 'kind' => 'image'], $field->toArray());
		$this->assertSame(['name' => 'file', 'type' => 'media'], $this->factory->fromArray(['name' => 'file', 'type' => 'media'])->toArray(), 'No kind takes any file.');

		$this->expectException(InvalidSchema::class);
		$this->expectExceptionMessage('unknown media kind "photo"');

		$this->factory->fromArray(['name' => 'poster', 'type' => 'media', 'kind' => 'photo']);
	}

	public function testResolvesCanonicalNamesAndAliases(): void
	{
		$result = $this->schema()->resolve([
			'title'  => 'Hello',
			'date'   => '2007-03-30 23:17:00 -5',
			'author' => 'justintadlock'
		], $this->context);

		$this->assertSame([
			'title'     => 'Hello',
			'published' => 1175314620,
			'authors'   => ['justintadlock'],
			'status'    => 'published'
		], $result->values);
		$this->assertFalse($result->hasErrors());
		$this->assertSame([
			'notice date: is read as "published".',
			'notice author: is read as "authors".'
		], self::messages($result->violations));
		$this->assertSame([], $result->violations(Severity::Warning));
	}

	public function testTheCanonicalNameWins(): void
	{
		$result = $this->schema()->resolve([
			'title'     => 'Hello',
			'date'      => '2001-01-01',
			'published' => '2020-01-01'
		], $this->context);

		$this->assertSame(1577858400, $result->values['published']);
		$this->assertSame(['notice date: is ignored because "published" is set.'], self::messages($result->violations));
	}

	public function testEmptyValuesAreMissing(): void
	{
		$result = $this->schema()->resolve(['title' => 'Hi', 'published' => null, 'date' => '2020-01-01'], $this->context);

		$this->assertSame(1577858400, $result->values['published']);

		$result = $this->schema()->resolve(['title' => 'Hi', 'published' => '', 'date' => '2020-01-01', 'view' => [], 'author' => ''], $this->context);

		$this->assertSame(['title' => 'Hi', 'published' => 1577858400, 'status' => 'published'], $result->values);
		$this->assertFalse($result->hasErrors());
	}

	public function testKeepsUndeclaredKeysAsExtraValues(): void
	{
		$result = $this->schema()->resolve(['title' => 'Hi', 'format' => 'gallery', 'social' => null, 5 => 'id'], $this->context);

		$this->assertSame(['format' => 'gallery', 'social' => null, '5' => 'id'], $result->extra);
		$this->assertFalse($result->hasErrors());
		$this->assertSame([
			'notice format: is not declared by the schema.',
			'notice social: is not declared by the schema.',
			'notice 5: is not declared by the schema.'
		], self::messages($result->violations));
	}

	public function testClosedSchemasRejectUndeclaredKeys(): void
	{
		$result = $this->schema(closed: true)->resolve(['title' => 'Hi', 'format' => 'gallery'], $this->context);

		$this->assertTrue($result->hasErrors());
		$this->assertSame(['error format: is not a field of this type.'], self::messages($result->violations(Severity::Error)));
	}

	public function testReportsInvalidAndMissingValues(): void
	{
		$result = $this->schema()->resolve(['date' => 'yesterday', 'status' => 'gone', 'view' => null], $this->context);

		$this->assertSame([], $result->values);
		$this->assertSame([
			'notice date: is read as "published".',
			'error date: must be a date such as 2026-05-01 15:40:00, not "yesterday".',
			'error status: must be one of published, draft, not "gone".',
			'error title: is required.'
		], self::messages($result->violations));
	}

	public function testReportsInvalidDefaults(): void
	{
		$schema = new Schema([new EnumField('status', ['a'])->default('b')]);

		$this->assertSame(['error status: has an invalid default: must be one of a, not "b".'], self::messages($schema->resolve([], $this->context)->violations));
	}

	public function testHydratesNormalizedValues(): void
	{
		$values = $this->schema()->hydrate(['published' => 1175314620, 'format' => 'gallery'], $this->context);

		$this->assertInstanceOf(DateTimeImmutable::class, $values['published']);
		$this->assertSame('gallery', $values['format']);
	}

	public function testFindsFieldsByNameOrAlias(): void
	{
		$schema = $this->schema();

		$this->assertSame('published', $schema->field('date')?->name);
		$this->assertSame('published', $schema->field('published')?->name);
		$this->assertNull($schema->field('format'));
		$this->assertTrue($schema->has('published'));
		$this->assertFalse($schema->has('date'));
	}

	public function testAddsAndMergesFields(): void
	{
		$schema = $this->schema()->with(new TextField('subtitle'), new TextField('title'));

		$this->assertSame(['title', 'published', 'status', 'authors', 'template', 'subtitle'], array_keys($schema->fields));
		$this->assertFalse($schema->fields['title']->required);

		$merged = $schema->merge(new Schema([new TextField('rating')], closed: true));

		$this->assertTrue($merged->closed);
		$this->assertTrue($merged->has('rating'));
	}

	public function testRejectsClashingNames(): void
	{
		$this->expectException(InvalidSchema::class);
		$this->expectExceptionMessage('Schema key "date" of field "date" is already used by field "published".');

		(void) $this->schema()->with(new DateField('date'));
	}

	public function testRejectsUnnamedFields(): void
	{
		$this->expectException(InvalidSchema::class);

		new Schema([new TextField()]);
	}

	public function testRoundTripsThroughDefinitions(): void
	{
		$schema = new Schema([
			...$this->schema()->fields,
			new ObjectField('seo', new Schema([new TextField('title')], closed: true)),
			new ListField('palette', new ColorField())
		]);

		$definition = $schema->toArray();
		$rebuilt    = $this->factory->schema($definition['fields']);

		$this->assertEquals($schema, $rebuilt);
		$item = $definition['fields'][6]['item'] ?? null;

		$this->assertIsArray($item);
		$this->assertSame(ColorField::class, $item['class'] ?? null);
		$this->assertSame(['fields' => [], 'closed' => true], new Schema(closed: true)->toArray());
	}

	public function testBuildsFieldsFromDataDefinitions(): void
	{
		$field = $this->factory->fromArray(['name' => 'rating', 'type' => 'number', 'integer' => true, 'min' => 1, 'aliases' => 'stars']);

		$this->assertSame(['name' => 'rating', 'type' => 'number', 'aliases' => ['stars'], 'integer' => true, 'min' => 1], $field->toArray());
		$this->assertSame('text', $this->factory->fromArray(['name' => 'plain'])->type());
	}

	public function testRejectsUnknownTypesAndBadSettings(): void
	{
		$cases = [
			[['name' => 'x', 'type' => 'color'], 'Field "x" has an unknown type "color".'],
			[['name' => 'x', 'required' => 'yes'], 'Field "x" "required" must be true or false.'],
			[['name' => 'x', 'type' => 'enum', 'options' => [1]], 'Field "x" "options" must be a list of strings.'],
			[['name' => 'x', 'type' => 'list', 'item' => ['a']], 'Field "x" "item" must be a map.']
		];

		foreach ($cases as [$definition, $message]) {
			try {
				$this->factory->fromArray($definition);
				$this->fail($message);
			} catch (InvalidSchema $e) {
				$this->assertSame($message, $e->getMessage());
			}
		}
	}

	public function testExtensionsRegisterFieldTypes(): void
	{
		$registry = new FieldRegistry(['color' => ColorField::class]);
		new FieldRegistrar($registry)->register();

		$field = new FieldFactory($registry)->fromArray(['name' => 'accent', 'type' => 'color']);

		$this->assertInstanceOf(ColorField::class, $field);
		$this->assertSame('#abcdef', $field->normalize('#ABCDEF', $this->context));
		$this->assertCount(12, $registry);
	}
}
