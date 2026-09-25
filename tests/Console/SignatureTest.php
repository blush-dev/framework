<?php

/**
 * Signature tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Console;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Blush\Console\Input\ArgumentDefinition;
use Blush\Console\Input\ArgvParser;
use Blush\Console\Input\InputBinder;
use Blush\Console\Input\OptionDefinition;
use Blush\Console\Input\Signature;
use Blush\Console\Input\ValueType;
use Blush\Console\InvalidCommand;
use Blush\Console\InvalidInput;
use Blush\Tests\Fixtures\Console\BadReturn;
use Blush\Tests\Fixtures\Console\BoolArgument;
use Blush\Tests\Fixtures\Console\Copy;
use Blush\Tests\Fixtures\Console\Greet;
use Blush\Tests\Fixtures\Console\Level;
use Blush\Tests\Fixtures\Console\NoAttribute;
use Blush\Tests\Fixtures\Console\OptionWithoutDefault;
use Blush\Tests\Fixtures\Console\RequiredAfterOptionalArgument;

#[CoversClass(Signature::class)]
#[CoversClass(InputBinder::class)]
#[CoversClass(ArgumentDefinition::class)]
#[CoversClass(OptionDefinition::class)]
#[CoversClass(ValueType::class)]
final class SignatureTest extends TestCase
{
	/**
	 * @param  list<string> $tokens
	 * @return array<string, mixed>
	 */
	private function bind(string $class, array $tokens): array
	{
		$definition = Signature::fromClass($class)->definition;

		return new InputBinder()->bind($definition, new ArgvParser()->parse($tokens, $definition));
	}

	public function testReadsTheSignature(): void
	{
		$signature = Signature::fromClass(Greet::class);

		$this->assertSame('greet', $signature->name);
		$this->assertSame('Greet people.', $signature->description);
		$this->assertSame(['hi'], $signature->aliases);
		$this->assertSame('greet [options] [--] <name> [<times>]', $signature->usage());

		$option = $signature->definition->option('dry-run');

		$this->assertNotNull($option);
		$this->assertSame('dryRun', $option->parameter);
		$this->assertSame('    --dry-run', $option->usage());
		$this->assertSame('-l, --level=LEVEL', $signature->definition->shortOption('l')?->usage());
		$this->assertSame('-t, --tag=TAG...', $signature->definition->shortOption('t')?->usage());
	}

	public function testBindsDefaults(): void
	{
		$this->assertSame([
			'name'   => 'Ada',
			'times'  => 1,
			'loud'   => false,
			'level'  => Level::Low,
			'ratio'  => null,
			'tag'    => [],
			'dryRun' => false
		], $this->bind(Greet::class, ['Ada']));
	}

	public function testBindsTypedValues(): void
	{
		$values = $this->bind(Greet::class, ['Ada', '3', '-s', '--level=high', '--ratio', '0.5', '-t', 'a', '-tb', '--dry-run']);

		$this->assertSame(3, $values['times']);
		$this->assertTrue($values['loud']);
		$this->assertSame(Level::High, $values['level']);
		$this->assertSame(0.5, $values['ratio']);
		$this->assertSame(['a', 'b'], $values['tag']);
		$this->assertTrue($values['dryRun']);
	}

	public function testBindsVariadicArguments(): void
	{
		$this->assertSame(['target' => 'dest', 'sources' => ['a', 'b']], $this->bind(Copy::class, ['dest', 'a', 'b']));
		$this->assertSame(['target' => 'dest', 'sources' => []], $this->bind(Copy::class, ['dest']));
		$this->assertSame('<target> [<sources>...]', implode(' ', array_map(
			static fn (ArgumentDefinition $argument): string => $argument->usage(),
			Signature::fromClass(Copy::class)->definition->arguments
		)));
	}

	/**
	 * @return iterable<string, array{list<string>, string}>
	 */
	public static function invalidInput(): iterable
	{
		yield 'missing argument' => [[], 'Missing required argument "name"'];
		yield 'too many' => [['Ada', '2', 'extra'], 'Too many arguments'];
		yield 'bad int' => [['Ada', 'two'], 'The "times" argument must be an integer; "two" given.'];
		yield 'bad float' => [['Ada', '--ratio=x'], 'must be a number'];
		yield 'bad enum' => [['Ada', '--level=mid'], 'must be one of: low, high'];
	}

	/**
	 * @param list<string> $tokens
	 */
	#[DataProvider('invalidInput')]
	public function testRejectsInvalidInput(array $tokens, string $message): void
	{
		$this->expectException(InvalidInput::class);
		$this->expectExceptionMessage($message);

		$this->bind(Greet::class, $tokens);
	}

	/**
	 * @return iterable<string, array{class-string, string}>
	 */
	public static function invalidCommands(): iterable
	{
		yield 'no attribute' => [NoAttribute::class, 'has no #['];
		yield 'bad return' => [BadReturn::class, 'must return'];
		yield 'bool argument' => [BoolArgument::class, 'use an option instead'];
		yield 'option without default' => [OptionWithoutDefault::class, 'without a default'];
		yield 'required after optional' => [RequiredAfterOptionalArgument::class, 'follows an optional argument'];
	}

	/**
	 * @param class-string $class
	 */
	#[DataProvider('invalidCommands')]
	public function testRejectsInvalidCommands(string $class, string $message): void
	{
		$this->expectException(InvalidCommand::class);
		$this->expectExceptionMessage($message);

		Signature::fromClass($class);
	}

	public function testCastsBooleans(): void
	{
		$this->assertTrue(ValueType::Bool->cast('yes', 'flag'));
		$this->assertFalse(ValueType::Bool->cast('0', 'flag'));
	}
}
