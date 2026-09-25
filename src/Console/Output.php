<?php

/**
 * Console output.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console;

use InvalidArgumentException;
use NoDiscard;

/**
 * Writes command output to a standard and an error stream. Styling uses
 * ANSI escape codes only when enabled, and each write can name the
 * verbosity it needs, so `-q` and `-v` work without commands checking.
 * Errors and warnings go to the error stream and ignore verbosity.
 *
 * Progress bars are planned for when a command needs one (M4's indexing).
 */
final readonly class Output
{
	/**
	 * @param resource $stdout
	 * @param resource $stderr
	 */
	public function __construct(
		private mixed $stdout,
		private mixed $stderr,
		public bool $ansi = false,
		public Verbosity $verbosity = Verbosity::Normal
	) {
		if (! is_resource($stdout) || ! is_resource($stderr)) {
			throw new InvalidArgumentException('Output streams must be resources.');
		}
	}

	/**
	 * Builds output for the process's standard streams. ANSI is on when
	 * standard output is a terminal and `NO_COLOR` isn't set in the given
	 * environment (https://no-color.org).
	 *
	 * @param array<string, string> $env The process environment.
	 */
	public static function forTerminal(array $env = []): self
	{
		$ansi = ($env['NO_COLOR'] ?? '') === '' && stream_isatty(STDOUT);

		return new self(STDOUT, STDERR, $ansi);
	}

	/**
	 * Returns a copy with ANSI styling turned on or off.
	 */
	#[NoDiscard]
	public function withAnsi(bool $ansi): self
	{
		return clone($this, ['ansi' => $ansi]);
	}

	/**
	 * Returns a copy with another verbosity.
	 */
	#[NoDiscard]
	public function withVerbosity(Verbosity $verbosity): self
	{
		return clone($this, ['verbosity' => $verbosity]);
	}

	/**
	 * Whether a message at the given level would be shown.
	 */
	public function shows(Verbosity $level): bool
	{
		return $this->verbosity->shows($level);
	}

	/**
	 * Writes text as-is to standard output.
	 */
	public function write(string $text, Verbosity $level = Verbosity::Normal): void
	{
		if ($this->shows($level)) {
			fwrite($this->stdout, $text);
		}
	}

	/**
	 * Writes text as-is to the error stream, regardless of verbosity.
	 */
	public function writeError(string $text): void
	{
		fwrite($this->stderr, $text);
	}

	/**
	 * Writes a line to standard output.
	 */
	public function line(string $text = '', Verbosity $level = Verbosity::Normal): void
	{
		$this->write($text . PHP_EOL, $level);
	}

	/**
	 * Writes one or more blank lines.
	 */
	public function newLine(int $count = 1, Verbosity $level = Verbosity::Normal): void
	{
		$this->write(str_repeat(PHP_EOL, max(0, $count)), $level);
	}

	/**
	 * Writes an informational line.
	 */
	public function info(string $text, Verbosity $level = Verbosity::Normal): void
	{
		$this->line($this->style($text, Style::Cyan), $level);
	}

	/**
	 * Writes a success line.
	 */
	public function success(string $text, Verbosity $level = Verbosity::Normal): void
	{
		$this->line($this->style($text, Style::Green), $level);
	}

	/**
	 * Writes a secondary, de-emphasized line.
	 */
	public function comment(string $text, Verbosity $level = Verbosity::Normal): void
	{
		$this->line($this->style($text, Style::Dim), $level);
	}

	/**
	 * Writes a warning line to the error stream.
	 */
	public function warning(string $text): void
	{
		$this->writeError($this->style($text, Style::Yellow) . PHP_EOL);
	}

	/**
	 * Writes an error line to the error stream.
	 */
	public function error(string $text): void
	{
		$this->writeError($this->style($text, Style::Red) . PHP_EOL);
	}

	/**
	 * Styles text when ANSI is on; returns it unchanged otherwise.
	 */
	public function style(string $text, Style ...$styles): string
	{
		return $this->ansi ? Style::apply($text, ...$styles) : $text;
	}

	/**
	 * Writes a table with a header row. Columns are padded to their
	 * widest cell.
	 *
	 * @param list<string>                                  $headers
	 * @param list<list<string|int|float|bool|null>>        $rows
	 */
	public function table(array $headers, array $rows, Verbosity $level = Verbosity::Normal): void
	{
		if (! $this->shows($level)) {
			return;
		}

		$cells  = array_map(
			static fn (array $row): array => array_map(
				static fn (string|int|float|bool|null $cell): string => match (true) {
					is_bool($cell) => $cell ? 'yes' : 'no',
					default        => (string) $cell
				},
				$row
			),
			$rows
		);
		$widths = [];

		foreach ([$headers, ...$cells] as $row) {
			foreach ($row as $column => $cell) {
				$widths[$column] = max($widths[$column] ?? 0, mb_strwidth($cell));
			}
		}

		$border = '+' . implode('+', array_map(static fn (int $width): string => str_repeat('-', $width + 2), $widths)) . '+';

		$this->line($border, $level);
		$this->line($this->tableRow($headers, $widths, true), $level);
		$this->line($border, $level);

		foreach ($cells as $row) {
			$this->line($this->tableRow($row, $widths), $level);
		}

		$this->line($border, $level);
	}

	/**
	 * Formats one table row.
	 *
	 * @param array<int, string> $row
	 * @param array<int, int>    $widths
	 */
	private function tableRow(array $row, array $widths, bool $header = false): string
	{
		$line = '|';

		foreach ($widths as $column => $width) {
			$cell  = $row[$column] ?? '';
			$pad   = str_repeat(' ', $width - mb_strwidth($cell));
			$line .= ' ' . ($header ? $this->style($cell, Style::Bold) : $cell) . $pad . ' |';
		}

		return $line;
	}
}
