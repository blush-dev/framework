<?php

/**
 * Prompt tests.
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
use Blush\Console\InvalidInput;
use Blush\Console\Output;
use Blush\Console\Prompt;

#[CoversClass(Prompt::class)]
final class PromptTest extends TestCase
{
	/** @var resource */
	private mixed $stdout;

	/**
	 * @param list<string> $lines
	 */
	private function prompt(array $lines = [], bool $interactive = true): Prompt
	{
		$this->stdout = $this->memory();
		$stderr       = $this->memory();
		$input        = $this->memory();

		fwrite($input, implode("\n", $lines) . "\n");
		rewind($input);

		return new Prompt(new Output($this->stdout, $stderr), $input, $interactive);
	}

	/**
	 * @return resource
	 */
	private function memory(): mixed
	{
		return fopen('php://memory', 'w+') ?: throw new RuntimeException('No memory stream.');
	}

	public function testNonInteractivePromptsReturnDefaults(): void
	{
		$prompt = $this->prompt(interactive: false);

		$this->assertSame('Ada', $prompt->ask('Name?', 'Ada'));
		$this->assertTrue($prompt->confirm('Sure?', true));
		$this->assertFalse($prompt->confirm('Sure?'));
		$this->assertSame('blue', $prompt->choice('Color?', ['red', 'blue'], 'blue'));
	}

	public function testNonInteractivePromptsWithoutDefaultsThrow(): void
	{
		$this->expectException(InvalidInput::class);

		$this->prompt(interactive: false)->ask('Name?');
	}

	public function testSecretsNeedAnInteractiveConsole(): void
	{
		$this->expectException(InvalidInput::class);

		$this->prompt(interactive: false)->secret('Password?');
	}

	public function testReadsAnswers(): void
	{
		$prompt = $this->prompt(['Grace', '', 'maybe', 'y', '9', '2', 'hunter2']);

		$this->assertSame('Grace', $prompt->ask('Name?'));
		$this->assertSame('dflt', $prompt->ask('Other?', 'dflt'));
		$this->assertTrue($prompt->confirm('Sure?'));
		$this->assertSame('blue', $prompt->choice('Color?', ['red', 'blue']));
		$this->assertSame('hunter2', $prompt->secret('Password?'));

		rewind($this->stdout);
		$this->assertStringContainsString('[2] blue', (string) stream_get_contents($this->stdout));
	}

	public function testThrowsAtTheEndOfInput(): void
	{
		$prompt = $this->prompt(['only']);
		$this->assertSame('only', $prompt->ask('First?'));

		$this->expectException(InvalidInput::class);

		$prompt->ask('Second?');
	}
}
