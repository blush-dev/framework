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
use Blush\Console\Frame;
use Blush\Console\InvalidInput;
use Blush\Console\Key;
use Blush\Console\KeyReader;
use Blush\Console\Output;
use Blush\Console\Prompt;
use Blush\Console\PromptCancelled;
use Blush\Console\PromptState;

#[CoversClass(Prompt::class)]
#[CoversClass(KeyReader::class)]
#[CoversClass(Key::class)]
#[CoversClass(Frame::class)]
#[CoversClass(PromptState::class)]
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

	/**
	 * Builds prompts drawn on a terminal, reading the given keys.
	 */
	private function drawn(string $keys): Prompt
	{
		$this->stdout = $this->memory();
		$input        = $this->memory();

		fwrite($input, $keys);
		rewind($input);

		return new Prompt(new Output($this->stdout, $this->memory(), width: 80), $this->memory(), true, new KeyReader($input, raw: false));
	}

	/**
	 * Returns the last line a drawn prompt left.
	 */
	private function answer(): string
	{
		rewind($this->stdout);

		$lines = explode("\r\033[J", (string) stream_get_contents($this->stdout));

		return str_replace("\033[?25h", '', (string) array_last($lines));
	}

	public function testDrawsTextAndEditsIt(): void
	{
		$this->assertSame('acme/nova', $this->drawn("acme/nobb\x7f\x7fva\r")->ask('Name', placeholder: 'acme/nova'));
		$this->assertSame("✓ Name  acme/nova\n", $this->answer(), 'One line with the answer.');

		$this->assertSame('Nova', $this->drawn("\r")->ask('Label', 'Nova'), 'Enter takes the default.');
		$this->assertSame('abc', $this->drawn("bc\033[Ha\r")->ask('Text'), 'Home moves the cursor.');
		$this->assertSame('ab', $this->drawn("abc\033[D\033[3~\r")->ask('Text'), 'Delete removes what\'s at the cursor.');
		$this->assertSame('café', $this->drawn("café\r")->ask('Text'), 'Characters of more than one byte.');
	}

	public function testShowsWhatsWrongUntilItsFixed(): void
	{
		$prompt = $this->drawn("\rnope\r\x7f\x7f\x7f\x7fyes\r");
		$answer = $prompt->ask('Word', validate: static fn (string $answer): ?string => $answer === 'yes' ? null : 'Say yes.');

		rewind($this->stdout);
		$drawn = (string) stream_get_contents($this->stdout);

		$this->assertSame('yes', $answer);
		$this->assertStringContainsString('An answer is required.', $drawn);
		$this->assertStringContainsString('Say yes.', $drawn);
	}

	public function testMasksSecrets(): void
	{
		$this->assertSame('hunter2', $this->drawn("hunter2\r")->secret('Password:'));
		$this->assertSame("✓ Password  •••••••\n", $this->answer());
	}

	public function testDrawsAToggle(): void
	{
		$this->assertTrue($this->drawn("\033[D\r")->confirm('Sure?'), 'An arrow switches it.');
		$this->assertSame("✓ Sure?  Yes\n", $this->answer());
		$this->assertFalse($this->drawn('n')->confirm('Sure?', true), 'n answers it.');
		$this->assertTrue($this->drawn("\r")->confirm('Sure?', true));
	}

	public function testDrawsAListToPickFrom(): void
	{
		$options = ['' => 'Standalone', 'blush/default' => 'Child of Default', 'acme/nova' => 'Child of Nova'];

		$this->assertSame('acme/nova', $this->drawn("\033[B\033[B\r")->select('Start', $options));
		$this->assertSame("✓ Start  Child of Nova\n", $this->answer());
		$this->assertSame('blush/default', $this->drawn("\r")->select('Start', $options, 'blush/default'), 'The default is highlighted.');
		$this->assertSame('acme/nova', $this->drawn("nov\r")->select('Start', $options), 'Typing filters.');
		$this->assertSame('', $this->drawn("\033[A\033[A\033[A\r")->select('Start', $options), 'Up wraps around.');
		$this->assertSame('blue', $this->drawn("\033[B\r")->choice('Color?', ['red', 'blue']));
	}

	public function testDrawsAListToPickSeveralFrom(): void
	{
		$options = ['editor' => 'Editor', 'author' => 'Author', 'viewer' => 'Viewer'];

		$this->assertSame(['editor', 'viewer'], $this->drawn(" \033[B\033[B \r")->multiselect('Roles', $options));
		$this->assertSame("✓ Roles  Editor, Viewer\n", $this->answer());
		$this->assertSame(['author'], $this->drawn(" \r")->multiselect('Roles', $options, ['editor', 'author']), 'Space takes a default off.');
		$this->assertSame([], $this->drawn("\r")->multiselect('Roles', $options));
	}

	public function testCtrlCCancels(): void
	{
		$prompt = $this->drawn("ab\x03");

		try {
			$prompt->ask('Name');
			$this->fail('It\'s cancelled.');
		} catch (PromptCancelled) {
			$this->assertSame("✗ Name\n", $this->answer(), 'The question, crossed out.');
		}
	}

	public function testThrowsWhenKeysEnd(): void
	{
		$this->expectException(InvalidInput::class);

		$this->drawn("ab\x04c\r")->ask('Name');
	}

	public function testAsksForOptionsALineAtATime(): void
	{
		$prompt = $this->prompt(['2', 'Child of Nova', '1, 3', 'nope', '']);
		$roles  = ['editor' => 'Editor', 'author' => 'Author', 'viewer' => 'Viewer'];

		$this->assertSame('blush/default', $prompt->select('Start', ['' => 'Standalone', 'blush/default' => 'Child of Default', 'acme/nova' => 'Child of Nova']));
		$this->assertSame('acme/nova', $prompt->select('Start', ['' => 'Standalone', 'acme/nova' => 'Child of Nova']), 'By label.');
		$this->assertSame(['editor', 'viewer'], $prompt->multiselect('Roles', $roles));
		$this->assertSame(['author'], $prompt->multiselect('Roles', $roles, ['author']), 'Nothing keeps the defaults, after an answer that isn\'t one.');
		$this->assertSame(['viewer'], $this->prompt(interactive: false)->multiselect('Roles', $roles, ['viewer']));
	}
}
