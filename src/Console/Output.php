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
 * `progress()` starts a progress bar, which draws only on a terminal, and
 * `steps()` a bar for each step of a longer task.
 *
 * Commands say what they show, and `Output` says how it looks (D-717):
 * `section()`, `status()`, `definitions()`, `table()`, `summary()`, and
 * `next()`, with `code()` for what can be typed. On a terminal (a known
 * `$width`), text wraps to it and outcomes are symbols; elsewhere,
 * nothing wraps and outcomes are words.
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
		public Verbosity $verbosity = Verbosity::Normal,
		public ?int $width = null,
		public Palette $palette = Palette::Basic,
		private OutputState $state = new OutputState()
	) {
		if (! is_resource($stdout) || ! is_resource($stderr)) {
			throw new InvalidArgumentException('Output streams must be resources.');
		}
	}

	/**
	 * The widest a label column grows before a long label pushes its
	 * row's text along instead.
	 */
	private const int LABEL_WIDTH = 32;

	/**
	 * Builds output for the process's standard streams. ANSI is on when
	 * standard output is a terminal and `NO_COLOR` isn't set in the given
	 * environment (https://no-color.org). On a terminal, the width is
	 * `COLUMNS`, else what `stty` says, else 80; and with ANSI on, the
	 * palette is the project's when the terminal can show it (D-718).
	 *
	 * @param array<string, string> $env The process environment.
	 */
	public static function forTerminal(array $env = []): self
	{
		$terminal = stream_isatty(STDOUT);
		$ansi     = ($env['NO_COLOR'] ?? '') === '' && $terminal;
		$palette  = $ansi ? Palette::detect($env, self::askBackground(...)) : Palette::Basic;

		return new self(STDOUT, STDERR, $ansi, width: $terminal ? self::terminalWidth($env) : null, palette: $palette);
	}

	/**
	 * Asks the terminal for its background color (OSC 11), followed by a
	 * question every terminal answers (DA1), so one that doesn't know
	 * OSC 11 is known by its answering only the second, and no reply is
	 * left to show up at the shell's prompt. Waits at most 200 ms, and
	 * puts the terminal's settings back.
	 */
	private static function askBackground(): ?string
	{
		if (! function_exists('shell_exec') || ! stream_isatty(STDIN)) {
			return null;
		}

		$saved = trim((string) shell_exec('stty -g < /dev/tty 2>/dev/null'));
		$tty   = $saved === '' ? false : @fopen('/dev/tty', 'r+');

		if ($tty === false) {
			return null;
		}

		shell_exec('stty -icanon -echo min 0 time 0 < /dev/tty 2>/dev/null');
		stream_set_blocking($tty, false);
		fwrite($tty, "\033]11;?\033\\\033[c");
		fflush($tty);

		$reply = '';
		$until = hrtime(true) + 200_000_000;

		while (hrtime(true) < $until && preg_match('/\033\[\?[\d;]*c/', $reply) !== 1) {
			$chunk  = fread($tty, 256);
			$reply .= $chunk === false ? '' : $chunk;

			if ($chunk === false || $chunk === '') {
				usleep(2000);
			}
		}

		shell_exec('stty ' . escapeshellarg($saved) . ' < /dev/tty 2>/dev/null');
		fclose($tty);

		return $reply === '' ? null : $reply;
	}

	/**
	 * Returns the terminal's width in columns.
	 *
	 * @param array<string, string> $env
	 */
	private static function terminalWidth(array $env): int
	{
		$columns = (int) ($env['COLUMNS'] ?? 0);

		if ($columns <= 0 && function_exists('shell_exec')) {
			$size    = trim((string) shell_exec('stty size 2>/dev/null'));
			$columns = preg_match('/^\d+ (\d+)$/', $size, $matches) === 1 ? (int) $matches[1] : 0;
		}

		return $columns > 0 ? $columns : 80;
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
		if ($this->shows($level) && $text !== '') {
			fwrite($this->stdout, $text);

			$this->state->remember($text);
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
	 * Writes a line saying something was done: on a terminal, after a
	 * `✓` (D-717).
	 */
	public function success(string $text, Verbosity $level = Verbosity::Normal): void
	{
		foreach ($this->message(Outcome::Pass, $text) as $line) {
			$this->line($line, $level);
		}
	}

	/**
	 * Writes a secondary, de-emphasized line.
	 */
	public function comment(string $text, Verbosity $level = Verbosity::Normal): void
	{
		$this->line($this->tone($text, Tone::Muted), $level);
	}

	/**
	 * Writes a warning line to the error stream, after a `!` on a
	 * terminal, and, when given, what to do about it, as `error()` does.
	 */
	public function warning(string $text, string $fix = ''): void
	{
		foreach ($this->message(Outcome::Warning, $text) as $line) {
			$this->writeError($line . PHP_EOL);
		}

		$this->fix($fix);
	}

	/**
	 * Writes an error line to the error stream, after a `✗` on a
	 * terminal: what went wrong, then, when given, what to do about it,
	 * with `code` spans marked in backticks.
	 */
	public function error(string $text, string $fix = ''): void
	{
		foreach ($this->message(Outcome::Failure, $text) as $line) {
			$this->writeError($line . PHP_EOL);
		}

		$this->fix($fix);
	}

	/**
	 * Writes what to do about an error or a warning, indented under it,
	 * dim, with `code` spans marked in backticks.
	 */
	private function fix(string $fix): void
	{
		foreach ($fix === '' ? [] : $this->marked($this->wrap($fix, 2), Tone::Muted) as $line) {
			$this->writeError("  {$line}" . PHP_EOL);
		}
	}

	/**
	 * Writes a section heading, after a blank line when something came
	 * before it.
	 */
	public function section(string $title, Verbosity $level = Verbosity::Normal): void
	{
		$this->gap($level);
		$this->line($this->tone($title, Tone::Heading), $level);
	}

	/**
	 * Writes check rows: each one's outcome, its label (aligned with the
	 * others), what was found, and a hint below. `$align` is the least
	 * width the labels take, to line up several sections.
	 *
	 * @param list<StatusLine> $lines
	 */
	public function status(array $lines, int $align = 0, Verbosity $level = Verbosity::Normal): void
	{
		if (! $this->shows($level) || $lines === []) {
			return;
		}

		$labels = min(self::LABEL_WIDTH, max($align, ...array_map(static fn (StatusLine $line): int => mb_strwidth($line->label), $lines)));
		$marks  = $this->symbols() ? 1 : max(array_map(static fn (Outcome $outcome): int => mb_strwidth($outcome->word()), Outcome::cases()));
		$indent = 2 + $marks + 1 + $labels + 2;

		foreach ($lines as $line) {
			$mark   = $this->symbols() ? $line->outcome->symbol() : str_pad($line->outcome->word(), $marks);
			$prefix = '  ' . $this->tone($mark, $line->outcome->tone()) . ' ' . $this->pad($line->label, $labels);

			$this->rows($prefix, $indent, $this->wrap($line->detail, $indent), $level);

			foreach ($line->hint === '' ? [] : $this->wrap($line->hint, $indent) as $hint) {
				$this->line(str_repeat(' ', $indent) . $this->tone($hint, Tone::Muted), $level);
			}
		}
	}

	/**
	 * Writes aligned `key  value` rows, the keys dim, or in the accent
	 * when they're what can be typed (`$code`). `$align` is the least
	 * width the keys take, to line up several lists.
	 *
	 * @param array<string, string> $rows
	 */
	public function definitions(array $rows, bool $code = false, int $align = 0, Verbosity $level = Verbosity::Normal): void
	{
		if (! $this->shows($level) || $rows === []) {
			return;
		}

		$keys   = array_map(strval(...), array_keys($rows));
		$width  = min(self::LABEL_WIDTH, max($align, ...array_map(mb_strwidth(...), $keys)));
		$indent = 2 + $width + 2;

		foreach ($rows as $key => $value) {
			$prefix = '  ' . $this->tone($this->pad((string) $key, $width), $code ? Tone::Code : Tone::Muted);

			$this->rows($prefix, $indent, $this->wrap($value, $indent), $level);
		}
	}

	/**
	 * Writes the line that sums up what a command found or did, after a
	 * blank line, in the color of its outcome: `$parts` joined by `·`.
	 * A failure goes to the error stream, so `-q` still shows it.
	 */
	public function summary(Outcome $outcome, string ...$parts): void
	{
		$text = ($this->symbols() ? $outcome->symbol() . ' ' : '') . $this->join(...$parts);

		if ($outcome === Outcome::Failure) {
			$this->writeError(($this->state->tail === '' ? '' : PHP_EOL) . $this->tone($text, Tone::Failure) . PHP_EOL);

			return;
		}

		$this->gap();
		$this->line($this->tone($text, $outcome->tone()));
	}

	/**
	 * Writes a hint at what to do next, after a blank line, dim, with
	 * `code` spans marked in backticks.
	 */
	public function next(string $text, Verbosity $level = Verbosity::Normal): void
	{
		$this->gap($level);

		foreach ($this->marked($this->wrap($text, 0), Tone::Muted) as $line) {
			$this->line($line, $level);
		}
	}

	/**
	 * Returns text in the accent for what can be typed: a command, an
	 * option, a path.
	 */
	public function code(string $text): string
	{
		return $this->tone($text, Tone::Code);
	}

	/**
	 * Joins parts of a line with `·` on a terminal, or commas elsewhere.
	 */
	public function join(string ...$parts): string
	{
		return implode($this->symbols() ? ' · ' : ', ', $parts);
	}

	/**
	 * Returns a count with its noun, singular or plural.
	 */
	public static function count(int $count, string $one, string $many): string
	{
		return sprintf('%s %s', number_format($count), $count === 1 ? $one : $many);
	}

	/**
	 * Returns a summary's counts of problems (`1 error`, `2 warnings`),
	 * leaving out kinds there are none of, or `no problems`. Notices are
	 * counted only when given.
	 *
	 * @return non-empty-list<string>
	 */
	public static function problems(int $errors, int $warnings, ?int $notices = null): array
	{
		$counts = array_filter([
			$errors > 0 ? self::count($errors, 'error', 'errors') : null,
			$warnings > 0 ? self::count($warnings, 'warning', 'warnings') : null,
			($notices ?? 0) > 0 ? self::count((int) $notices, 'notice', 'notices') : null
		]);

		return $counts === [] ? ['no problems'] : array_values($counts);
	}

	/**
	 * Returns text in a tone, from the palette, when ANSI is on.
	 */
	public function tone(string $text, ?Tone $tone): string
	{
		return $tone === null || ! $this->ansi || $text === '' ? $text : "\033[{$this->palette->codes($tone)}m{$text}\033[0m";
	}

	/**
	 * Starts a progress bar over `$total` steps (which `update()` can
	 * change once it's known), after what it's doing when it's named.
	 */
	public function progress(int $total = 0, string $label = ''): ProgressBar
	{
		return new ProgressBar($this, $total, label: $label);
	}

	/**
	 * Starts showing the steps of a longer task, a bar for each, to pass
	 * to a service as its progress (`$steps(...)`).
	 */
	public function steps(): ProgressSteps
	{
		return new ProgressSteps($this);
	}

	/**
	 * Writes a table: a bold header row with a dim rule under it, and
	 * columns two spaces apart, padded to their widest cell.
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

		$rule   = $this->symbols() ? '─' : '-';

		$this->line($this->tableRow($headers, $widths, Tone::Heading), $level);
		$this->line($this->tone(implode('  ', array_map(static fn (int $width): string => str_repeat($rule, $width), $widths)), Tone::Muted), $level);

		foreach ($cells as $row) {
			$this->line($this->tableRow($row, $widths), $level);
		}
	}

	/**
	 * Formats one table row.
	 *
	 * @param array<int, string> $row
	 * @param array<int, int>    $widths
	 */
	private function tableRow(array $row, array $widths, ?Tone $tone = null): string
	{
		$cells = [];

		foreach ($widths as $column => $width) {
			$cell    = $row[$column] ?? '';
			$cells[] = $this->tone($cell, $tone) . str_repeat(' ', $width - mb_strwidth($cell));
		}

		return rtrim(implode('  ', $cells));
	}

	/**
	 * Whether outcomes are drawn as symbols: on a terminal, or with ANSI
	 * forced on.
	 */
	private function symbols(): bool
	{
		return $this->ansi || $this->width !== null;
	}

	/**
	 * Returns a message's lines: on a terminal, its outcome's symbol in
	 * color, then the text, wrapped under itself; elsewhere, the text in
	 * the outcome's color.
	 *
	 * @return list<string>
	 */
	private function message(Outcome $outcome, string $text): array
	{
		if (! $this->symbols()) {
			return array_map(fn (string $line): string => $this->tone($line, $outcome->tone()), $this->wrap($text, 0));
		}

		$lines = $this->wrap($text, 2);

		return array_map(
			fn (int $index, string $line): string => ($index === 0 ? $this->tone($outcome->symbol(), $outcome->tone()) . ' ' : '  ') . $line,
			array_keys($lines),
			$lines
		);
	}

	/**
	 * Writes a blank line when something came before and it wasn't one.
	 */
	private function gap(Verbosity $level = Verbosity::Normal): void
	{
		if ($this->state->tail !== '' && $this->state->tail !== PHP_EOL . PHP_EOL) {
			$this->write(PHP_EOL, $level);
		}
	}

	/**
	 * Writes a row's first line after its prefix, and the rest indented
	 * under it.
	 *
	 * @param list<string> $lines
	 */
	private function rows(string $prefix, int $indent, array $lines, Verbosity $level): void
	{
		foreach ($lines as $index => $line) {
			$this->line(rtrim(($index === 0 ? $prefix . '  ' : str_repeat(' ', $indent)) . $line), $level);
		}
	}

	/**
	 * Pads text to a width, leaving longer text as it is.
	 */
	private function pad(string $text, int $width): string
	{
		return $text . str_repeat(' ', max(0, $width - mb_strwidth($text)));
	}

	/**
	 * Splits text into lines: at its line breaks, and, on a terminal, at
	 * the spaces that keep each line within the width after `$indent`.
	 *
	 * @return list<string>
	 */
	private function wrap(string $text, int $indent): array
	{
		$lines = [];

		foreach (explode("\n", $text) as $paragraph) {
			if ($this->width === null) {
				$lines[] = $paragraph;

				continue;
			}

			$room = max(20, $this->width - $indent);
			$line = '';

			foreach (explode(' ', $paragraph) as $word) {
				$next = $line === '' ? $word : "{$line} {$word}";

				if ($line !== '' && mb_strwidth(str_replace('`', '', $next)) > $room) {
					$lines[] = $line;
					$next    = $word;
				}

				$line = $next;
			}

			$lines[] = $line;
		}

		return $lines;
	}

	/**
	 * Draws the backtick-marked spans of lines in the accent and the rest
	 * in a tone, dropping the backticks. A span may run across lines.
	 *
	 * @param  list<string> $lines
	 * @return list<string>
	 */
	private function marked(array $lines, ?Tone $tone): array
	{
		$code = false;

		foreach ($lines as $index => $line) {
			$text = '';

			foreach (explode('`', $line) as $part => $span) {
				$code  = $part > 0 ? ! $code : $code;
				$text .= $span === '' ? '' : $this->tone($span, $code ? Tone::Code : $tone);
			}

			$lines[$index] = $text;
		}

		return $lines;
	}
}
