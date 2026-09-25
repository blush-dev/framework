<?php

/**
 * Argv parser tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Console;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Console\Input\ArgvParser;
use Blush\Console\Input\InputDefinition;
use Blush\Console\Input\OptionDefinition;
use Blush\Console\Input\ParsedInput;
use Blush\Console\Input\ValueType;
use Blush\Console\InvalidInput;

#[CoversClass(ArgvParser::class)]
#[CoversClass(ParsedInput::class)]
#[CoversClass(InputDefinition::class)]
final class ArgvParserTest extends TestCase
{
	private function definition(): InputDefinition
	{
		return new InputDefinition([], [
			new OptionDefinition('force', 'force', ValueType::Bool, short: 'f'),
			new OptionDefinition('verbose', 'verbose', ValueType::Bool, short: 'v'),
			new OptionDefinition('port', 'port', ValueType::Int, short: 'p'),
			new OptionDefinition('tag', 'tag', ValueType::String, list: true)
		]);
	}

	/**
	 * @param list<string> $tokens
	 */
	private function parse(array $tokens, bool $lenient = false): ParsedInput
	{
		return new ArgvParser()->parse($tokens, $this->definition(), $lenient);
	}

	public function testParsesLongOptions(): void
	{
		$input = $this->parse(['a', '--force', '--port', '80', '--tag=x', '--tag', 'y', 'b']);

		$this->assertSame(['a', 'b'], $input->arguments);
		$this->assertSame([true], $input->options['force']);
		$this->assertSame(['80'], $input->options['port']);
		$this->assertSame(['x', 'y'], $input->options['tag']);
	}

	public function testParsesShortOptions(): void
	{
		$this->assertSame(['8000'], $this->parse(['-p8000'])->options['port']);
		$this->assertSame(['8000'], $this->parse(['-p=8000'])->options['port']);
		$this->assertSame(['8000'], $this->parse(['-p', '8000'])->options['port']);

		$bundled = $this->parse(['-vvf', '-vp9']);

		$this->assertSame(3, $bundled->count('verbose'));
		$this->assertTrue($bundled->has('force'));
		$this->assertSame('9', $bundled->last('port'));
	}

	public function testNegatesFlags(): void
	{
		$input = $this->parse(['--force', '--no-force']);

		$this->assertSame([true, false], $input->options['force']);
		$this->assertFalse($input->last('force'));
	}

	public function testTerminatorMakesEverythingAnArgument(): void
	{
		$input = $this->parse(['--', '--force', '-p', '-']);

		$this->assertSame(['--force', '-p', '-'], $input->arguments);
		$this->assertSame([], $input->options);
	}

	public function testRejectsUnknownOptions(): void
	{
		$this->expectException(InvalidInput::class);
		$this->expectExceptionMessage('"--nope" option does not exist');

		$this->parse(['--nope']);
	}

	public function testRejectsUnknownShortOptions(): void
	{
		$this->expectException(InvalidInput::class);

		$this->parse(['-x']);
	}

	public function testLenientModeSkipsUnknownOptions(): void
	{
		$input = $this->parse(['--nope', '-xf', 'arg'], lenient: true);

		$this->assertSame(['arg'], $input->arguments);
		$this->assertTrue($input->has('force'));
	}

	public function testRequiresValues(): void
	{
		$this->expectException(InvalidInput::class);
		$this->expectExceptionMessage('requires a value');

		$this->parse(['--port']);
	}

	public function testFlagsRejectValues(): void
	{
		$this->expectException(InvalidInput::class);
		$this->expectExceptionMessage('does not accept a value');

		$this->parse(['--force=yes']);
	}
}
