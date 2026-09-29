<?php

/**
 * Env file.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Env;

use NoDiscard;
use Blush\Support\Filesystem;
use Blush\Support\FilesystemException;

/**
 * A `.env` file as text, for tools that change it (`init`). Unlike `Env`,
 * which only reads, it keeps the file's comments, order, and formatting:
 * `with()` rewrites the one line that sets a variable, or adds a line at the
 * end, and leaves every other line alone.
 */
final readonly class EnvFile
{
	public function __construct(
		public string $path,
		public string $contents = ''
	) {}

	/**
	 * Loads a file. A missing file loads as empty.
	 *
	 * @throws EnvException When the file exists but can't be read.
	 */
	public static function load(string $path): self
	{
		if (! is_file($path)) {
			return new self($path);
		}

		$contents = @file_get_contents($path);

		return $contents === false
			? throw new EnvException(sprintf('Unable to read "%s".', $path))
			: new self($path, $contents);
	}

	/**
	 * Whether the file exists on disk.
	 */
	public function exists(): bool
	{
		return is_file($this->path);
	}

	/**
	 * Returns a variable's value as the file sets it, or `null` when the
	 * file doesn't set it. References to the process environment expand to
	 * empty strings.
	 *
	 * @throws EnvException When the file can't be parsed.
	 */
	public function get(string $name): ?string
	{
		return new EnvParser()->parse($this->contents)[$name] ?? null;
	}

	/**
	 * Whether the file sets a variable to a value other than `''`.
	 *
	 * @throws EnvException When the file can't be parsed.
	 */
	public function filled(string $name): bool
	{
		return ($this->get($name) ?? '') !== '';
	}

	/**
	 * Returns a copy that sets a variable. The last line that sets it is
	 * rewritten (keeping an `export` prefix, and double quotes if the old
	 * value had them); otherwise a line is added at the end.
	 *
	 * @throws EnvException When the name is invalid, or the old value spans
	 *                      several lines.
	 */
	#[NoDiscard]
	public function with(string $name, string $value): self
	{
		if (preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $name) !== 1) {
			throw new EnvException(sprintf('"%s" is not a valid environment variable name.', $name));
		}

		$lines   = explode("\n", $this->contents);
		$pattern = '/^(\s*(?:export\s+)?' . preg_quote($name, '/') . '\s*=\s*)(.*)$/';
		$match   = null;

		foreach ($lines as $index => $line) {
			if (preg_match($pattern, $line, $parts) === 1) {
				$match = [$index, $parts[1], $parts[2]];
			}
		}

		if ($match === null) {
			$contents = $this->contents === '' || str_ends_with($this->contents, "\n")
				? $this->contents
				: "{$this->contents}\n";

			return clone($this, ['contents' => $contents . $name . '=' . self::quote($value) . "\n"]);
		}

		[$index, $prefix, $old] = $match;

		if (self::isMultiLine($old)) {
			throw new EnvException(sprintf('%s spans several lines in "%s"; change it by hand.', $name, $this->path));
		}

		$lines[$index] = $prefix . self::quote($value, str_starts_with($old, '"'));

		return clone($this, ['contents' => implode("\n", $lines)]);
	}

	/**
	 * Writes the file atomically.
	 *
	 * @throws FilesystemException When it can't be written.
	 */
	public function save(Filesystem $filesystem): void
	{
		$filesystem->writeAtomic($this->path, $this->contents);
	}

	/**
	 * Writes a value so `EnvParser` reads it back unchanged: bare when it
	 * has only safe characters (and no quotes were asked for), otherwise
	 * double-quoted with escapes.
	 */
	private static function quote(string $value, bool $quoted = false): string
	{
		if (! $quoted && preg_match('#^[A-Za-z0-9_./:@+,-]*$#', $value) === 1) {
			return $value;
		}

		return '"' . strtr($value, ['\\' => '\\\\', '"' => '\\"', '$' => '\\$', "\n" => '\\n', "\r" => '\\r']) . '"';
	}

	/**
	 * Whether a value's text opens a quote it doesn't close on its line.
	 */
	private static function isMultiLine(string $value): bool
	{
		$quote = $value[0] ?? '';

		return match ($quote) {
			"'"     => ! str_contains(substr($value, 1), "'"),
			'"'     => preg_match('/^"(?:[^"\\\\]|\\\\.)*"/', $value) !== 1,
			default => false
		};
	}
}
