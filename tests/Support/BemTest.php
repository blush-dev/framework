<?php

/**
 * BEM class name tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Support;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Blush\Support\Bem;
use Blush\Support\BemClass;
use Blush\Support\InvalidClassName;

#[CoversClass(Bem::class)]
#[CoversClass(BemClass::class)]
#[CoversClass(InvalidClassName::class)]
final class BemTest extends TestCase
{
	public function testNamesTheBlockItsElementsAndModifiers(): void
	{
		$bem = new Bem('pagination');

		$this->assertSame('pagination', (string) $bem->block());
		$this->assertSame('pagination pagination--next', (string) $bem->modifier('next'));
		$this->assertSame('pagination__item', (string) $bem->element('item'));
		$this->assertSame('pagination__item pagination__item--current', (string) $bem->element('item')->modifier('current'));
	}

	public function testChainsModifiersAndUtilities(): void
	{
		$bem = new Bem('block-callout');

		$this->assertSame('block-callout block-callout--info block-callout--wide', (string) $bem->block()->modifier('info', 'wide', 'info', ''), 'Each modifier is added once, and empty ones are left out.');
		$this->assertSame('block-callout block-callout--info alignwide', (string) $bem->block()->modifier('info')->with(' alignwide '));
		$this->assertSame('block-callout one two', (string) $bem->block()->with(['one', '', 'two']));
		$this->assertSame('audio-card playlist__card', (string) new Bem('audio-card')->block()->with(new Bem('playlist')->element('card')), 'Another block\'s class mixes in.');
		$this->assertSame('block-callout__title block-callout__title--on', (string) $bem->element('title')->when(true, 'on')->when(false, 'off'));
	}

	public function testEachStepIsANewClass(): void
	{
		$item    = new Bem('menu')->element('item');
		$current = $item->modifier('current');

		$this->assertSame('menu__item', (string) $item);
		$this->assertSame('menu__item menu__item--current', (string) $current);
	}

	public function testPrintsWhereAStringDoes(): void
	{
		$this->assertSame('menu__link menu__link--current', attr(new Bem('menu')->element('link')->modifier('current')));
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function invalidNames(): array
	{
		return [
			'empty'       => [''],
			'a space'     => ['two words'],
			'a quote'     => ['x"y'],
			'a leading -' => ['-item'],
			'a period'    => ['item.current']
		];
	}

	#[DataProvider('invalidNames')]
	public function testRefusesAnInvalidBlockName(string $name): void
	{
		$this->expectException(InvalidClassName::class);

		new Bem($name);
	}

	#[DataProvider('invalidNames')]
	public function testRefusesAnInvalidElementName(string $name): void
	{
		$bem = new Bem('pagination');

		$this->expectException(InvalidClassName::class);
		$this->expectExceptionMessage('isn\'t a valid BEM element name');

		(void) $bem->element($name);
	}

	public function testRefusesAnInvalidModifierButSkipsAnEmptyOne(): void
	{
		$item = new Bem('pagination')->element('item');

		$this->assertSame('pagination__item', (string) $item->modifier(''));

		$this->expectException(InvalidClassName::class);
		$this->expectExceptionMessage('isn\'t a valid BEM modifier name');

		(void) $item->modifier('two words');
	}
}
