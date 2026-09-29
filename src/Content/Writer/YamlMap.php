<?php

/**
 * YAML map editor.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Writer;

use NoDiscard;
use Symfony\Component\Yaml\Yaml;

/**
 * A YAML map as text (front matter, or a YAML entry), edited one
 * top-level key at a time so everything else stays as the author wrote
 * it: comments, key order, blank lines, aligned colons (`title     :`),
 * and quoting (D-228).
 *
 * A key's entry is its line plus the lines that belong to it: indented
 * lines and `- ` list items below it (and blank lines between them).
 * Setting a key rewrites only that entry, under the key the text already
 * uses (the first of the names it's given that's present, so a 1.x
 * `date` stays `date`), keeping the space before its colon; a new key is
 * added at the end. Values are dumped by Symfony's YAML dumper: inline
 * for scalars, lists, and maps, a literal block for text with line
 * breaks, nothing after the colon for `null`, dates (YAML timestamps)
 * unquoted, and text that needs quotes in double quotes, as people write
 * them.
 *
 * It doesn't check its own work; `DocumentEditor` parses the result.
 */
final readonly class YamlMap
{
	/**
	 * Matches a top-level key's line: group 1 is the key, group 2 the
	 * space before the colon.
	 */
	private const string KEY = '/^([A-Za-z0-9_][A-Za-z0-9_.\/-]*)([ \t]*):(?:[ \t]|$)/';

	/**
	 * Matches a YAML timestamp, which is written unquoted, as people
	 * write dates.
	 */
	public const string TIMESTAMP = '/^\d{4}-\d{2}-\d{2}(?:[Tt ]\d{1,2}:\d{2}:\d{2}(?:\.\d+)?(?:[ \t]*(?:Z|[-+]\d{1,2}(?::?\d{2})?))?)?$/';

	/**
	 * @param list<string> $lines The text's lines, without line endings.
	 */
	private function __construct(
		private array $lines,
		private string $eol
	) {}

	/**
	 * Reads text, keeping its line endings (`\r\n` or `\n`).
	 */
	public static function fromText(string $text): self
	{
		$eol   = str_contains($text, "\r\n") ? "\r\n" : "\n";
		$lines = $text === '' ? [] : explode($eol, $text);

		// A final line ending leaves an empty last line; it's added back.
		if ($lines !== [] && end($lines) === '') {
			array_pop($lines);
		}

		return new self($lines, $eol);
	}

	/**
	 * Returns the text, ending with a line ending when it has lines.
	 */
	public function text(): string
	{
		return $this->lines === [] ? '' : implode($this->eol, $this->lines) . $this->eol;
	}

	/**
	 * Returns the line ending the text uses.
	 */
	public function eol(): string
	{
		return $this->eol;
	}

	/**
	 * Returns the first of the keys that's present, or `null`.
	 *
	 * @param list<string> $keys
	 */
	public function present(array $keys): ?string
	{
		foreach ($keys as $key) {
			if ($this->find($key) !== null) {
				return $key;
			}
		}

		return null;
	}

	/**
	 * Returns a copy that sets a value under the first of `$keys` that's
	 * present, or adds it as `$keys[0]`.
	 *
	 * @param list<string> $keys The field's name first, then its aliases.
	 */
	#[NoDiscard]
	public function with(array $keys, mixed $value): self
	{
		$key   = $this->present($keys) ?? $keys[0] ?? throw new WriteException('A key is needed to set a value.');
		$range = $this->find($key);
		$space = $range === null ? '' : $range[2];
		$entry = self::dump($key, $value, $space);

		$lines = $range === null
			? [...$this->lines, ...$entry]
			: [...array_slice($this->lines, 0, $range[0]), ...$entry, ...array_slice($this->lines, $range[1])];

		return clone($this, ['lines' => $lines]);
	}

	/**
	 * Returns a copy without any of the keys.
	 *
	 * @param list<string> $keys
	 */
	#[NoDiscard]
	public function without(array $keys): self
	{
		$lines = $this->lines;

		foreach ($keys as $key) {
			$range = self::range($lines, $key);

			if ($range !== null) {
				array_splice($lines, $range[0], $range[1] - $range[0]);
			}
		}

		return clone($this, ['lines' => $lines]);
	}

	/**
	 * Returns a key's entry as [first line, line after the last, the
	 * space before its colon], or `null`.
	 *
	 * @return ?array{int, int, string}
	 */
	private function find(string $key): ?array
	{
		return self::range($this->lines, $key);
	}

	/**
	 * Finds a key's entry in some lines.
	 *
	 * @param  list<string> $lines
	 * @return ?array{int, int, string}
	 */
	private static function range(array $lines, string $key): ?array
	{
		foreach ($lines as $index => $line) {
			if (preg_match(self::KEY, $line, $match) !== 1 || $match[1] !== $key) {
				continue;
			}

			$end = $index + 1;

			while ($end < count($lines) && self::belongs($lines, $end)) {
				$end++;
			}

			// Blank lines at the end belong to what follows.
			while ($end > $index + 1 && trim($lines[$end - 1]) === '') {
				$end--;
			}

			return [$index, $end, $match[2]];
		}

		return null;
	}

	/**
	 * Whether a line continues the entry above it: it's indented, a
	 * top-level list item, or blank with such a line after it.
	 *
	 * @param list<string> $lines
	 */
	private static function belongs(array $lines, int $index): bool
	{
		$line = $lines[$index];

		if ($line !== '' && ($line[0] === ' ' || $line[0] === "\t" || $line === '-' || str_starts_with($line, '- '))) {
			return true;
		}

		if (trim($line) !== '') {
			return false;
		}

		for ($next = $index + 1; $next < count($lines); $next++) {
			if (trim($lines[$next]) !== '') {
				return self::belongs($lines, $next);
			}
		}

		return false;
	}

	/**
	 * Dumps a key and value as lines, with the given space before the
	 * colon.
	 *
	 * @return list<string>
	 */
	private static function dump(string $key, mixed $value, string $space): array
	{
		if (is_string($value) && preg_match(self::TIMESTAMP, $value) === 1) {
			$lines = ["{$key}: {$value}"];
		} else {
			$lines = explode("\n", rtrim(Yaml::dump([$key => $value], 1, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK | Yaml::DUMP_NULL_AS_EMPTY | Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE), "\n"));

			// Text that needs quotes gets double quotes, as people write
			// them; a JSON string is a valid double-quoted YAML string.
			if (is_string($value) && count($lines) === 1 && str_starts_with(substr($lines[0], strlen($key) + 2), "'")) {
				$lines = ["{$key}: " . json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)];
			}
		}

		$lines[0] = rtrim($key . $space . substr($lines[0], strlen($key)));

		return $lines;
	}
}
