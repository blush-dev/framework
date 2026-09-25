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
use Blush\Console\Output;
use Blush\Console\ProgressBar;
use Blush\Console\Style;
use Blush\Console\Verbosity;

#[CoversClass(Output::class)]
#[CoversClass(Style::class)]
#[CoversClass(Verbosity::class)]
#[CoversClass(ProgressBar::class)]
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
		$output->info('Info');
		$output->comment('Note');
		$output->newLine();
		$output->write('raw');

		$this->assertSame('Done' . PHP_EOL . 'Info' . PHP_EOL . 'Note' . PHP_EOL . PHP_EOL . 'raw', $this->read($this->stdout));
	}

	public function testStylesWithAnsi(): void
	{
		$output = new Output($this->stdout, $this->stderr)->withAnsi(true);

		$output->success('Done');

		$this->assertSame("\033[32mDone\033[0m" . PHP_EOL, $this->read($this->stdout));
		$this->assertSame("\033[1;31mx\033[0m", Style::apply('x', Style::Bold, Style::Red));
		$this->assertSame('x', Style::apply('x'));
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
			'+-------+-----+',
			'| Name  | On  |',
			'+-------+-----+',
			'| alpha | yes |',
			'| b     |     |',
			'+-------+-----+'
		]) . PHP_EOL, $this->read($this->stdout));
	}

	public function testDrawsProgressBarsOnlyWithAnsi(): void
	{
		$plain = new Output($this->stdout, $this->stderr)->progress(10);
		$plain->advance(5);
		$plain->finish();

		$this->assertSame('', $this->read($this->stdout));

		$bar = new Output($this->stdout, $this->stderr)->withAnsi(true)->progress();
		$bar->update(1, 4);
		$bar->update(1, 4);
		$bar->advance();
		$bar->update(10);
		$bar->finish();

		$this->assertSame(
			"\r\033[2K[=======>                      ] 1/4  25%"
			. "\r\033[2K[===============>              ] 2/4  50%"
			. "\r\033[2K[==============================] 4/4 100%"
			. "\r\033[2K",
			$this->read($this->stdout)
		);
	}

	public function testQuietOutputHidesProgressBars(): void
	{
		$bar = new Output($this->stdout, $this->stderr, true, Verbosity::Quiet)->progress(2);
		$bar->advance();

		$this->assertSame('', $this->read($this->stdout));
		$this->assertSame('[===============>              ] 1/2  50%', $bar->render());
	}
}
