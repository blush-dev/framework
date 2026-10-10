<?php

/**
 * Palette tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Console;

use RuntimeException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Console\Output;
use Blush\Console\Palette;
use Blush\Console\Tone;

#[CoversClass(Palette::class)]
final class PaletteTest extends TestCase
{
	public function testKeepsTheTerminalsColorsWithoutExactColor(): void
	{
		$this->assertSame(Palette::Basic, Palette::detect(['COLORFGBG' => '15;0']), 'No COLORTERM.');
		$this->assertSame(Palette::Basic, Palette::detect(['COLORTERM' => 'truecolor']), 'An unknown background.');
		$this->assertSame(Palette::Basic, Palette::detect(['COLORTERM' => 'truecolor'], static fn (): string => "\033[?62;c"), 'A terminal that only answers DA1.');
	}

	public function testDetectsTheBackground(): void
	{
		$asked = static fn (): string => throw new RuntimeException('COLORFGBG is read first.');

		$this->assertSame(Palette::Dark, Palette::detect(['COLORTERM' => 'truecolor', 'COLORFGBG' => '15;0'], $asked));
		$this->assertSame(Palette::Light, Palette::detect(['COLORTERM' => '24bit', 'COLORFGBG' => '0;default;15'], $asked));
		$this->assertSame(Palette::Light, Palette::detect(['COLORTERM' => 'truecolor'], static fn (): string => "\033]11;rgb:f7f7/f9f9/fdfd\033\\\033[?62;c"));
		$this->assertSame(Palette::Dark, Palette::detect(['COLORTERM' => 'truecolor'], static fn (): string => "\033]11;rgb:05/06/0e\007\033[?1;2c"));
	}

	public function testReadsColorFgBg(): void
	{
		$this->assertSame(Palette::Dark, Palette::fromColorFgBg('7;8'));
		$this->assertSame(Palette::Light, Palette::fromColorFgBg('0;7'));
		$this->assertNull(Palette::fromColorFgBg('0;default'));
		$this->assertNull(Palette::fromColorFgBg('0;99'));
	}

	public function testDrawsTonesFromThePalette(): void
	{
		$stdout = fopen('php://memory', 'w+') ?: throw new RuntimeException('No memory stream.');
		$output = new Output($stdout, $stdout, true, palette: Palette::Dark);

		$this->assertSame("\033[38;2;79;216;255mblush\033[0m", $output->code('blush'));
		$this->assertSame("\033[1mUsage\033[0m", $output->tone('Usage', Tone::Heading));
		$this->assertSame('38;2;15;118;110', Palette::Light->codes(Tone::Pass));
		$this->assertSame('31', Palette::Basic->codes(Tone::Failure));
		$this->assertSame('plain', $output->withAnsi(false)->code('plain'), 'No color without ANSI.');
	}
}
