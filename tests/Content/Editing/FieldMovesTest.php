<?php

/**
 * Field moves tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Content\Editing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Content\Editing\FieldMoves;
use Blush\Field\Fields\ObjectField;
use Blush\Field\Fields\TextField;
use Blush\Field\Schema;

#[CoversClass(FieldMoves::class)]
final class FieldMovesTest extends TestCase
{
	private static function group(string $name, string ...$fields): ObjectField
	{
		return new ObjectField($name, new Schema(array_map(static fn (string $field): TextField => new TextField($field), $fields)));
	}

	public function testFindsAFieldMovedIntoAGroupAndOutOfOne(): void
	{
		$this->assertSame(
			[['from' => ['servings'], 'to' => ['details', 'servings']]],
			FieldMoves::between([new TextField('servings'), self::group('details')], [self::group('details', 'servings')])
		);

		$this->assertSame(
			[['from' => ['details', 'servings'], 'to' => ['servings']]],
			FieldMoves::between([self::group('details', 'servings')], [new TextField('servings')])
		);

		$this->assertSame(
			[['from' => ['a', 'servings'], 'to' => ['b', 'servings']]],
			FieldMoves::between([self::group('a', 'servings'), self::group('b')], [self::group('a'), self::group('b', 'servings')]),
			'From one group to another.'
		);
	}

	public function testLeavesWhatDidntMove(): void
	{
		$fields = [new TextField('title'), self::group('details', 'servings')];

		$this->assertSame([], FieldMoves::between($fields, $fields));
		$this->assertSame([], FieldMoves::between($fields, [new TextField('title')]), 'A field removed isn\'t moved.');
		$this->assertSame([], FieldMoves::between([self::group('a', 'title'), self::group('b', 'title')], [new TextField('title')]), 'A key in two places isn\'t followed.');
	}

	public function testAGroupThatMovesTakesItsFields(): void
	{
		$this->assertSame(
			[['from' => ['details'], 'to' => ['outer', 'details']]],
			FieldMoves::between([self::group('details', 'servings'), self::group('outer')], [new ObjectField('outer', new Schema([self::group('details', 'servings')]))])
		);
	}
}
