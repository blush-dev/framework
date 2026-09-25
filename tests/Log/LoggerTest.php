<?php

/**
 * Logger tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Log;

use DateTimeImmutable;
use RuntimeException;
use Psr\Log\InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Clock\FrozenClock;
use Blush\Log\FileWriter;
use Blush\Log\InvalidLogLevel;
use Blush\Log\Level;
use Blush\Log\Logger;
use Blush\Tests\Fixtures\Log\MemoryWriter;
use Blush\Tests\TemporaryDirectory;

#[CoversClass(Logger::class)]
#[CoversClass(Level::class)]
#[CoversClass(FileWriter::class)]
final class LoggerTest extends TestCase
{
	use TemporaryDirectory;

	private MemoryWriter $writer;

	protected function setUp(): void
	{
		$this->writer = new MemoryWriter();
	}

	private function logger(Level $threshold = Level::Debug): Logger
	{
		return new Logger($this->writer, new FrozenClock('2026-09-25T10:00:00+00:00'), $threshold);
	}

	public function testFormatsALine(): void
	{
		$this->logger()->error('Page {slug} failed', ['slug' => 'about', 'id' => 7]);

		$this->assertSame(['[2026-09-25T10:00:00+00:00] blush.ERROR: Page about failed {"id":7}'], $this->writer->lines);
	}

	public function testDropsMessagesBelowTheThreshold(): void
	{
		$logger = $this->logger(Level::Warning);

		$logger->info('skipped');
		$logger->warning('kept');
		$logger->critical('kept too');

		$this->assertCount(2, $this->writer->lines);
	}

	public function testInterpolatesValuesOfEveryKind(): void
	{
		$this->logger()->info('{null} {bool} {date} {object} {array} {missing}', [
			'null'   => null,
			'bool'   => false,
			'date'   => new DateTimeImmutable('2026-01-02T03:04:05+00:00'),
			'object' => new RuntimeException('boom'),
			'array'  => ['a' => 1]
		]);

		$this->assertStringEndsWith(
			'INFO: null false 2026-01-02T03:04:05+00:00 RuntimeException: boom {"a":1} {missing}',
			$this->writer->lines[0]
		);
	}

	public function testAppendsExceptionChains(): void
	{
		$this->logger()->error('Failed', ['exception' => new RuntimeException('outer', 0, new RuntimeException('inner'))]);

		$this->assertStringContainsString("Failed\nRuntimeException: outer in", $this->writer->lines[0]);
		$this->assertStringContainsString("Caused by:\nRuntimeException: inner in", $this->writer->lines[0]);
	}

	public function testRejectsUnknownLevels(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectException(InvalidLogLevel::class);

		$this->logger()->log('loud', 'nope');
	}

	public function testAcceptsLevelCasesAndStrings(): void
	{
		$logger = $this->logger();

		$logger->log(Level::Notice, 'case');
		$logger->log('NOTICE', 'string');

		$this->assertCount(2, $this->writer->lines);
	}

	public function testFileWriterAppendsAndCreatesDirectories(): void
	{
		$path   = $this->temporaryDirectory() . '/logs/blush.log';
		$writer = new FileWriter($path);

		$writer->write(Level::Error, 'one');
		$writer->write(Level::Error, 'two');

		$this->assertSame("one\ntwo\n", file_get_contents($path));
	}
}
