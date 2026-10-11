<?php

/**
 * Field type tests.
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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Blush\Field\Definition;
use Blush\Field\Field;
use Blush\Field\FieldContext;
use Blush\Field\Fields\BoolField;
use Blush\Field\Fields\DateField;
use Blush\Field\Fields\EnumField;
use Blush\Field\Fields\ListField;
use Blush\Field\Fields\MarkdownField;
use Blush\Field\Fields\MediaField;
use Blush\Field\Fields\NumberField;
use Blush\Field\Fields\ObjectField;
use Blush\Field\Fields\ReferenceField;
use Blush\Field\Fields\SlugField;
use Blush\Field\Fields\TextField;
use Blush\Field\InvalidField;
use Blush\Field\InvalidSchema;
use Blush\Field\Schema;
use Blush\Field\TextFormat;

#[CoversClass(Field::class)]
#[CoversClass(Definition::class)]
#[CoversClass(TextField::class)]
#[CoversClass(MarkdownField::class)]
#[CoversClass(DateField::class)]
#[CoversClass(BoolField::class)]
#[CoversClass(NumberField::class)]
#[CoversClass(EnumField::class)]
#[CoversClass(ListField::class)]
#[CoversClass(ReferenceField::class)]
#[CoversClass(MediaField::class)]
#[CoversClass(SlugField::class)]
#[CoversClass(ObjectField::class)]
#[CoversClass(InvalidField::class)]
final class FieldsTest extends TestCase
{
	private FieldContext $context;

	protected function setUp(): void
	{
		$this->context = new FieldContext(new DateTimeZone('America/Chicago'));
	}

	/**
	 * @return array<string, array{Field, mixed, mixed}>
	 */
	public static function validValues(): array
	{
		return [
			'text'                  => [new TextField('title'), 'Hello', 'Hello'],
			'text from a number'    => [new TextField('title'), 404, '404'],
			'web address'           => [new TextField('site', TextFormat::Url), ' https://example.com/a ', 'https://example.com/a'],
			'path on the site'      => [new TextField('site', TextFormat::Url), '/about', '/about'],
			'email address'         => [new TextField('email', TextFormat::Email), 'me@example.com', 'me@example.com'],
			'icon name'             => [new TextField('badge', TextFormat::Icon), 'house', 'house'],
			'markdown'              => [new MarkdownField('summary'), '*hi*', '*hi*'],
			'media'                 => [new MediaField('image'), ' 019A2B3C-4D5E-7F60-8A9B-0C1D2E3F4A5B ', '019a2b3c-4d5e-7f60-8a9b-0c1d2e3f4a5b'],
			'media path, from before ids' => [new MediaField('image'), ' /user/media/a.jpg ', '/user/media/a.jpg'],
			'slug'                  => [new SlugField('slug'), 'about-me', 'about-me'],
			'bool'                  => [new BoolField('featured'), true, true],
			'bool from a string'    => [new BoolField('featured'), 'No', false],
			'bool from an int'      => [new BoolField('featured'), 1, true],
			'number'                => [new NumberField('rating'), '4.5', 4.5],
			'whole number'          => [new NumberField('rating', integer: true), 3.0, 3],
			'enum ignores case'     => [new EnumField('status', ['published', 'draft']), 'Draft', 'draft'],
			'date with an offset'   => [new DateField('published'), '2007-03-30 23:17:00 -5', 1175314620],
			'date in the site zone' => [new DateField('published'), '2003-04-15', 1050382800],
			'ISO date'              => [new DateField('published'), '2007-03-31T04:17:00+00:00', 1175314620],
			'timestamp'             => [new DateField('published'), 1175314620, 1175314620],
			'date object'           => [new DateField('published'), new DateTimeImmutable('@1175314620'), 1175314620],
			'date without a time'   => [new DateField('day', time: false), '2007-03-30 23:17:00 -5', 1175230800],
			'number in steps'       => [new NumberField('hours', min: 0, step: 0.5), 1.5, 1.5],
			'list within limits'    => [new ListField('tags', min: 1, max: 2), ['a'], ['a']],
			'list'                  => [new ListField('class'), ['wide', '', 'dark'], ['wide', 'dark']],
			'scalar as a list'      => [new ListField('class'), 'wide', ['wide']],
			'list of numbers'       => [new ListField('scores', new NumberField()), [1, '2'], [1, 2]],
			'references'            => [new ReferenceField('category', 'category'), ['Book Reviews', 'books', 'books'], ['book-reviews', 'books']],
			'scalar reference'      => [new ReferenceField('era', 'era'), 'college', ['college']],
			'single reference'      => [new ReferenceField('series', 'series', multiple: false), ['Dune'], 'dune'],
			'object'                => [new ObjectField('tokens'), ['color' => 'red'], ['color' => 'red']],
			'typed object'          => [
				new ObjectField('seo', new Schema([new BoolField('index'), new TextField('title')])),
				['index' => 'yes', 'title' => 7, 'other' => 'kept'],
				['other' => 'kept', 'index' => true, 'title' => '7']
			]
		];
	}

	#[DataProvider('validValues')]
	public function testNormalizesValidValues(Field $field, mixed $value, mixed $expected): void
	{
		$this->assertSame($expected, $field->normalize($value, $this->context));
	}

	/**
	 * @return array<string, array{Field, mixed, string}>
	 */
	public static function invalidValues(): array
	{
		return [
			'text from a list'       => [new TextField('title'), ['a'], 'must be text, not a list.'],
			'not a web address'      => [new TextField('site', TextFormat::Url), 'example.com', 'must be a web address'],
			'protocol-relative'      => [new TextField('site', TextFormat::Url), '//example.com', 'must be a web address'],
			'not an email address'   => [new TextField('email', TextFormat::Email), 'nope', 'must be an email address'],
			'icon name with spaces'  => [new TextField('badge', TextFormat::Icon), 'a house', 'must be an icon name'],
			'number off its steps'   => [new NumberField('seats', min: 0, step: 5), 7, 'must go in steps of 5 from 0.'],
			'too few items'          => [new ListField('tags', min: 2), ['a'], 'must have at least 2 items.'],
			'too many items'         => [new ListField('tags', max: 1), ['a', 'b'], 'must have at most 1 item.'],
			'media without a value'  => [new MediaField('image'), ' ', 'must be a media file\'s id'],
			'slug with spaces'       => [new SlugField('slug'), 'About Me', '"About Me" is not a slug; try "about-me".'],
			'bool from junk'         => [new BoolField('featured'), 'maybe', 'must be true or false, not "maybe".'],
			'number from text'       => [new NumberField('rating'), 'five', 'must be a number, not string.'],
			'fractional integer'     => [new NumberField('rating', integer: true), 2.5, 'must be a whole number'],
			'number below minimum'   => [new NumberField('rating', min: 1), 0, 'must be at least 1.'],
			'number above maximum'   => [new NumberField('rating', max: 5), 6, 'must be at most 5.'],
			'unknown option'         => [new EnumField('status', ['published', 'draft']), 'gone', 'must be one of published, draft, not "gone".'],
			'relative date'          => [new DateField('published'), 'tomorrow', 'must be a date such as'],
			'impossible date'        => [new DateField('published'), '2020-01-01 25:99', 'is not a valid date.'],
			'map as a list'          => [new ListField('class'), ['a' => 'b'], 'must be a list, not a map.'],
			'bad list item'          => [new ListField('scores', new NumberField()), [1, 'x'], 'item 2 must be a number'],
			'map as references'      => [new ReferenceField('tags', 'tag'), ['a' => 'b'], 'not a map.'],
			'nested references'      => [new ReferenceField('tags', 'tag'), [['a']], 'found a list.'],
			'several single refs'    => [new ReferenceField('series', 'series', multiple: false), ['a', 'b'], 'takes one slug'],
			'list as an object'      => [new ObjectField('tokens'), ['a'], 'must be a map, not a list.'],
			'bad nested object'      => [new ObjectField('seo', new Schema([new BoolField('index')])), ['index' => 'eh'], 'index: must be true or false'],
			'closed object'          => [new ObjectField('seo', new Schema([], closed: true)), ['x' => 1], 'x: is not a field of this type.']
		];
	}

	#[DataProvider('invalidValues')]
	public function testRejectsInvalidValues(Field $field, mixed $value, string $message): void
	{
		$this->expectException(InvalidField::class);
		$this->expectExceptionMessage($message);

		$field->normalize($value, $this->context);
	}

	public function testHydratesDatesInTheSiteTimezone(): void
	{
		$date = new DateField('published')->hydrate(1175314620, $this->context);

		$this->assertInstanceOf(DateTimeImmutable::class, $date);
		$this->assertSame('2007-03-30T23:17:00-05:00', $date->format(DATE_ATOM));

		$dates = new ListField('dates', new DateField())->hydrate([1175314620], $this->context);

		$this->assertIsArray($dates);
		$this->assertInstanceOf(DateTimeImmutable::class, $dates[0]);

		$object = new ObjectField('event', new Schema([new DateField('on')]))->hydrate(['on' => 1175314620, 'x' => 1], $this->context);

		$this->assertIsArray($object);
		$this->assertInstanceOf(DateTimeImmutable::class, $object['on']);
		$this->assertSame(1, $object['x']);
	}

	public function testSharedSettingsAreImmutableCopies(): void
	{
		$field = new DateField('published');
		$copy  = $field->aliases('date', 'posted')->required()->default('2020-01-01')->labeled('Published')->described('When it went live.');

		$this->assertSame([], $field->aliases);
		$this->assertSame(['date', 'posted'], $copy->aliases);
		$this->assertTrue($copy->required);
		$this->assertSame('2020-01-01', $copy->default);
		$this->assertSame('Published', $copy->label);
		$this->assertSame('When it went live.', $copy->description);
		$this->assertSame('posted', $copy->named('posted')->name);
	}

	public function testExportsDefinitions(): void
	{
		$this->assertSame(['name' => 'title', 'type' => 'text'], new TextField('title')->toArray());
		$this->assertSame(
			['name' => 'rating', 'type' => 'number', 'required' => true, 'integer' => true, 'max' => 5],
			new NumberField('rating', integer: true, max: 5)->required()->toArray()
		);
		$this->assertSame(
			['name' => 'template', 'type' => 'list', 'aliases' => ['view'], 'item' => ['type' => 'text']],
			new ListField('template')->aliases('view')->toArray()
		);
		$this->assertSame(
			['name' => 'series', 'type' => 'reference', 'class' => ReferenceField::class, 'to' => 'series', 'multiple' => false],
			new ReferenceField('series', 'series', multiple: false)->toArray(),
			'Not a registered type, so it names its class for compiled caches.'
		);
	}

	public function testRejectsBadDefinitions(): void
	{
		$this->expectException(InvalidSchema::class);

		new NumberField('rating', min: 5, max: 1);
	}

	public function testEnumsNeedOptions(): void
	{
		$this->expectException(InvalidSchema::class);

		new EnumField('status');
	}
}
