<?php

/**
 * Output tests.
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
use Blush\Console\Outcome;
use Blush\Console\Output;
use Blush\Console\ProgressBar;
use Blush\Console\ProgressSteps;
use Blush\Console\StatusLine;
use Blush\Console\Style;
use Blush\Console\Tone;
use Blush\Console\Verbosity;

#[CoversClass(Output::class)]
#[CoversClass(Style::class)]
#[CoversClass(Tone::class)]
#[CoversClass(Outcome::class)]
#[CoversClass(StatusLine::class)]
#[CoversClass(Verbosity::class)]
#[CoversClass(ProgressBar::class)]
#[CoversClass(ProgressSteps::class)]
final class OutputTest extends TestCase
{
	/** @var resource */
	private mixed $stdout;

	/** @var resource */
	private mixed $stderr;

	protected function setUp(): void
	{
		$this->stdout = fopen('php://memory', 'w+') ?: throw new RuntimeException('No memory stream.');
		$this->stderr = fopen('php://memory', 'w+') ?: throw new RuntimeException('No memory stream.');
	}

	/**
	 * @param resource $stream
	 */
	private function read(mixed $stream): string
	{
		rewind($stream);

		return (string) stream_get_contents($stream);
	}

	public function testWritesPlainTextWithoutAnsi(): void
	{
		$output = new Output($this->stdout, $this->stderr);

		$output->success('Done');
		$output->comment('Note');
		$output->newLine();
		$output->write('raw');

		$this->assertSame('Done' . PHP_EOL . 'Note' . PHP_EOL . PHP_EOL . 'raw', $this->read($this->stdout));
	}

	public function testStylesWithAnsi(): void
	{
		$output = new Output($this->stdout, $this->stderr)->withAnsi(true);

		$output->success('Done');

		$this->assertSame("\033[32m✓\033[0m Done" . PHP_EOL, $this->read($this->stdout), 'A symbol, in color, before the text (D-717).');
		$this->assertSame("\033[1;31mx\033[0m", Style::apply('x', Style::Bold, Style::Red));
		$this->assertSame('x', Style::apply('x'));
	}

	public function testMarksMessagesOnATerminal(): void
	{
		$output = new Output($this->stdout, $this->stderr, width: 20);

		$output->success('Created the account for Ada.');
		$output->warning('Careful');
		$output->error('Bad');

		$this->assertSame('✓ Created the account' . PHP_EOL . '  for Ada.' . PHP_EOL, $this->read($this->stdout), 'Wrapped under its text.');
		$this->assertSame('! Careful' . PHP_EOL . '✗ Bad' . PHP_EOL, $this->read($this->stderr));
	}

	public function testErrorsAndWarningsGoToTheErrorStream(): void
	{
		$output = new Output($this->stdout, $this->stderr, verbosity: Verbosity::Quiet);

		$output->error('Bad');
		$output->warning('Careful');
		$output->line('hidden');

		$this->assertSame('', $this->read($this->stdout));
		$this->assertSame('Bad' . PHP_EOL . 'Careful' . PHP_EOL, $this->read($this->stderr));
	}

	public function testRespectsVerbosity(): void
	{
		$output = new Output($this->stdout, $this->stderr)->withVerbosity(Verbosity::Verbose);

		$output->line('normal');
		$output->line('verbose', Verbosity::Verbose);
		$output->line('debug', Verbosity::Debug);

		$this->assertSame('normal' . PHP_EOL . 'verbose' . PHP_EOL, $this->read($this->stdout));
		$this->assertTrue($output->shows(Verbosity::VeryVerbose) === false);
	}

	public function testRendersTables(): void
	{
		$output = new Output($this->stdout, $this->stderr);

		$output->table(['Name', 'On'], [['alpha', true], ['b', null]]);

		$this->assertSame(implode(PHP_EOL, [
			'Name   On',
			'-----  ---',
			'alpha  yes',
			'b'
		]) . PHP_EOL, $this->read($this->stdout));
	}

	public function testDrawsTableRulesOnATerminal(): void
	{
		$output = new Output($this->stdout, $this->stderr, width: 80);

		$output->table(['Name'], [['a']]);

		$this->assertSame('Name' . PHP_EOL . '────' . PHP_EOL . 'a' . PHP_EOL, $this->read($this->stdout));
	}

	public function testWritesSectionsWithBlankLinesBetween(): void
	{
		$output = new Output($this->stdout, $this->stderr);

		$output->section('First');
		$output->line('one');
		$output->section('Second');
		$output->newLine();
		$output->section('Third');

		$this->assertSame('First' . PHP_EOL . 'one' . PHP_EOL . PHP_EOL . 'Second' . PHP_EOL . PHP_EOL . 'Third' . PHP_EOL, $this->read($this->stdout), 'One blank line, never two.');
	}

	public function testAClearedBarLeavesNoBlankLine(): void
	{
		$output = new Output($this->stdout, $this->stderr, width: 40);
		$bar    = $output->progress(2);

		$bar->update(1);
		$bar->finish();
		$output->summary(Outcome::Pass, 'Indexed 2 entries');

		$this->assertStringEndsWith("\r\033[2K✓ Indexed 2 entries" . PHP_EOL, $this->read($this->stdout), 'Nothing came before the bar, so no blank line (D-722).');

		$after = new Output($this->stdout, $this->stderr, width: 40);
		$after->line('Before');

		$bar = $after->progress(2);
		$bar->update(1);
		$bar->finish();
		$after->summary(Outcome::Pass, 'Done');

		$this->assertStringEndsWith("\r\033[2K" . PHP_EOL . '✓ Done' . PHP_EOL, $this->read($this->stdout), 'One blank line after what came before.');
	}

	public function testWritesStatusRowsWithWordsOffATerminal(): void
	{
		$output = new Output($this->stdout, $this->stderr);

		$output->status([
			new StatusLine(Outcome::Pass, 'PHP', '8.5'),
			new StatusLine(Outcome::Warning, 'Cron', 'Not set up.', 'Add it.')
		]);

		$this->assertSame(implode(PHP_EOL, [
			'  ok      PHP   8.5',
			'  warning Cron  Not set up.',
			'                Add it.'
		]) . PHP_EOL, $this->read($this->stdout));
	}

	public function testWritesStatusRowsWithSymbolsAndWrapsOnATerminal(): void
	{
		$output = new Output($this->stdout, $this->stderr, width: 30);

		$output->status([new StatusLine(Outcome::Failure, 'Jobs', 'Cron is not set up on this site yet.')], align: 6);

		$this->assertSame(implode(PHP_EOL, [
			'  ✗ Jobs    Cron is not set up',
			'            on this site yet.'
		]) . PHP_EOL, $this->read($this->stdout));
	}

	public function testWritesDefinitions(): void
	{
		$output = new Output($this->stdout, $this->stderr);

		$output->definitions(['Name' => 'Ada', 'Email address' => 'ada@example.test']);

		$this->assertSame('  Name           Ada' . PHP_EOL . '  Email address  ada@example.test' . PHP_EOL, $this->read($this->stdout));
	}

	public function testWritesSummariesInTheirOutcomesStream(): void
	{
		$output = new Output($this->stdout, $this->stderr);

		$output->line('rows');
		$output->summary(Outcome::Warning, '2 checks', '1 warning');
		$output->summary(Outcome::Failure, '1 failure');

		$this->assertSame('rows' . PHP_EOL . PHP_EOL . '2 checks, 1 warning' . PHP_EOL, $this->read($this->stdout));
		$this->assertSame(PHP_EOL . '1 failure' . PHP_EOL, $this->read($this->stderr));
		$this->assertSame('2 checks', Output::count(2, 'check', 'checks'));
		$this->assertSame(Outcome::Failure, Outcome::worst(Outcome::Pass, Outcome::Failure, Outcome::Warning));
		$this->assertSame(Outcome::Pass, Outcome::worst());
	}

	public function testMarksCodeInHintsAndFixes(): void
	{
		$output = new Output($this->stdout, $this->stderr)->withAnsi(true);

		$output->next('Run `blush doctor`.');
		$output->error('Bad', 'Run `blush init`.');

		$this->assertSame("\033[2mRun \033[0m\033[36mblush doctor\033[0m\033[2m.\033[0m" . PHP_EOL, $this->read($this->stdout));
		$this->assertSame("\033[31m✗\033[0m Bad" . PHP_EOL . "  \033[2mRun \033[0m\033[36mblush init\033[0m\033[2m.\033[0m" . PHP_EOL, $this->read($this->stderr));
	}

	public function testKeepsCodeSpansAcrossWrappedLines(): void
	{
		$output = new Output($this->stdout, $this->stderr, width: 24);

		$output->next('Run `blush help --global` for more.');

		$this->assertSame('Run blush help --global' . PHP_EOL . 'for more.' . PHP_EOL, $this->read($this->stdout));
	}

	public function testDrawsProgressBarsOnATerminal(): void
	{
		$plain = new Output($this->stdout, $this->stderr)->progress(10);
		$plain->advance(5);
		$plain->finish();

		$this->assertSame('', $this->read($this->stdout), 'Not off a terminal.');

		$bar = new Output($this->stdout, $this->stderr, width: 20)->progress();
		$bar->update(1, 4);
		$bar->update(1, 4);
		$bar->advance();
		$bar->update(10);
		$bar->finish();

		$this->assertSame(
			"\r\033[2K" . self::bar(5, 20) . '  1 / 4   25%'
			. "\r\033[2K" . self::bar(10, 20) . '  2 / 4   50%'
			. "\r\033[2K" . self::bar(20, 20) . '  4 / 4  100%'
			. "\r\033[2K",
			$this->read($this->stdout),
			'Drawn without color under NO_COLOR, redrawn when its percentage changes, at least 20 wide (D-722).'
		);
	}

	public function testFitsTheBarToTheTerminal(): void
	{
		$bar = new Output($this->stdout, $this->stderr, width: 200)->progress(2);
		$bar->advance();

		$this->assertSame(self::bar(20, 40) . '  1 / 2   50%', $bar->render(), 'At most 40 wide.');
	}

	public function testQuietOutputHidesProgressBars(): void
	{
		$bar = new Output($this->stdout, $this->stderr, false, Verbosity::Quiet, width: 20)->progress(2);
		$bar->advance();

		$this->assertSame('', $this->read($this->stdout));
		$this->assertSame(self::bar(10, 20) . '  1 / 2   50%', $bar->render());
	}

	public function testNamesABarAndSpinsUntilItsTotalIsKnown(): void
	{
		$bar = new Output($this->stdout, $this->stderr, width: 20)->progress(label: 'Reading files');
		$bar->update(0);

		$this->assertSame("\r\033[2K⠋ Reading files", $this->read($this->stdout));

		$bar->update(1203);

		$this->assertMatchesRegularExpression('/^\S Reading files  1,203$/u', $bar->render());

		$bar->update(1203, 4009);

		$this->assertMatchesRegularExpression('/^\S Reading files  ━{6}╸─{13}  1,203 \/ 4,009   30%$/u', $bar->render(), 'Counts read with separators.');
	}

	public function testStepsLeaveALineForEachStepDone(): void
	{
		$steps = new Output($this->stdout, $this->stderr)->withAnsi(true)->steps();
		$steps('Checking the index', 0, 0);
		$steps('Writing dates', 0, 2);
		$steps('Writing dates', 2, 2);
		$steps->finish();

		$drawn = $this->read($this->stdout);

		$this->assertStringStartsWith("\r\033[2K⠋ Checking the index\r\033[2K\r\033[2K⠋ Writing dates  ", $drawn, 'A step that counted nothing is cleared.');
		$this->assertStringContainsString("\033[36m╸\033[0m\033[2m" . str_repeat('─', 29) . "\033[0m  0 / 2    0%", $drawn, 'Done in the accent, the rest dim.');
		$this->assertMatchesRegularExpression('/\r\033\[2K\033\[32m✓\033\[0m Writing dates  \033\[2m2 · \d+ ms\033\[0m\n$/u', $drawn, 'Done, with its count and time.');
	}

	/**
	 * Returns a bar of a width, filled so far, as drawn without color.
	 */
	private static function bar(int $filled, int $width): string
	{
		return str_repeat('━', $filled) . ($filled < $width ? '╸' . str_repeat('─', $width - $filled - 1) : '');
	}

	public function testStepsDrawNothingWithoutATerminal(): void
	{
		$steps = new Output($this->stdout, $this->stderr)->steps();
		$steps('Writing dates', 1, 2);
		$steps->finish();

		$this->assertSame('', $this->read($this->stdout));
	}
}
