<?php

/**
 * Env parser.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Env;

/**
 * Parses `.env` files. The format is the common subset most tools agree on:
 *
 *     # A comment
 *     APP_ENV=production
 *     export APP_URL=https://example.com   # `export` is allowed and ignored
 *     APP_NAME="My Site"                   # escapes and ${VAR} expand
 *     SECRET='lit$eral'                    # single quotes are literal
 *     GREETING="Hello
 *     world"                               # quoted values may span lines
 *     EMPTY=
 *
 * Unquoted values end at ` #` (an inline comment) and are trimmed. `${NAME}`
 * expands in unquoted and double-quoted values, from variables defined
 * earlier in the file or from `$environment`. An undefined variable expands to
 * an empty string.
 */
final class EnvParser
{
	/**
	 * Parses `.env` contents into a name-to-value map.
	 *
	 * @param  array<string, string> $environment Variables available to `${NAME}` expansion.
	 * @return array<string, string>
	 * @throws EnvException On a syntax error, with its line number.
	 */
	public function parse(string $contents, array $environment = []): array
	{
		$contents = str_replace(["\r\n", "\r"], "\n", $contents);

		// Strip a UTF-8 byte order mark.
		if (str_starts_with($contents, "\u{FEFF}")) {
			$contents = substr($contents, 3);
		}

		$values = [];
		$offset = 0;
		$length = strlen($contents);
		$line   = 1;

		while ($offset < $length) {
			$end  = strpos($contents, "\n", $offset);
			$end  = $end === false ? $length : $end;
			$text = trim(substr($contents, $offset, $end - $offset));

			if ($text === '' || str_starts_with($text, '#')) {
				$offset = $end + 1;
				$line++;
				continue;
			}

			if (preg_match('/^(?:export\s+)?([A-Za-z_][A-Za-z0-9_.]*)\s*=\s*/', $text, $match) !== 1) {
				throw new EnvException(sprintf('Invalid .env syntax on line %d: expected NAME=value.', $line));
			}

			$name  = $match[1];
			$start = $offset + (int) strpos(substr($contents, $offset), $match[0]) + strlen($match[0]);
			$quote = $contents[$start] ?? '';

			if ($quote === '"' || $quote === "'") {
				[$raw, $after] = $this->readQuoted($contents, $start, $quote, $line);

				$lineEnd  = strpos($contents, "\n", $after);
				$lineEnd  = $lineEnd === false ? $length : $lineEnd;
				$trailing = trim(substr($contents, $after, $lineEnd - $after));

				if ($trailing !== '' && ! str_starts_with($trailing, '#')) {
					throw new EnvException(sprintf('Invalid .env syntax on line %d: unexpected text after the closing quote.', $line));
				}

				$values[$name] = $quote === "'"
					? $raw
					: $this->expand($this->unescape($raw), [...$environment, ...$values]);

				$line  += substr_count($contents, "\n", $offset, $lineEnd - $offset) + 1;
				$offset = $lineEnd + 1;
				continue;
			}

			$raw = trim(substr($contents, $start, $end - $start));
			$raw = trim((string) preg_replace('/\s+#.*$/', '', $raw));

			$values[$name] = $this->expand($raw, [...$environment, ...$values]);

			$offset = $end + 1;
			$line++;
		}

		return $values;
	}

	/**
	 * Reads a quoted value starting at the opening quote. Returns the raw
	 * contents and the offset just past the closing quote.
	 *
	 * @return array{0: string, 1: int}
	 * @throws EnvException When the quote is never closed.
	 */
	private function readQuoted(string $contents, int $start, string $quote, int $line): array
	{
		$position = $start + 1;
		$length   = strlen($contents);

		while ($position < $length) {
			$char = $contents[$position];

			if ($char === '\\' && $quote === '"') {
				$position += 2;
				continue;
			}

			if ($char === $quote) {
				return [substr($contents, $start + 1, $position - $start - 1), $position + 1];
			}

			$position++;
		}

		throw new EnvException(sprintf('Invalid .env syntax on line %d: unterminated quoted value.', $line));
	}

	/**
	 * Applies the escapes double-quoted values support.
	 */
	private function unescape(string $value): string
	{
		return (string) preg_replace_callback(
			'/\\\\(.)/s',
			static fn (array $match): string => match ($match[1]) {
				'n'     => "\n",
				'r'     => "\r",
				't'     => "\t",
				'"'     => '"',
				'\\'    => '\\',
				'$'     => "\0DOLLAR\0",
				default => '\\' . $match[1]
			},
			$value
		);
	}

	/**
	 * Expands `${NAME}` references. An escaped `\$` (marked by
	 * `unescape()`) is restored as a literal dollar sign.
	 *
	 * @param array<string, string> $variables
	 */
	private function expand(string $value, array $variables): string
	{
		$value = (string) preg_replace_callback(
			'/\$\{([A-Za-z_][A-Za-z0-9_.]*)\}/',
			static fn (array $match): string => $variables[$match[1]] ?? '',
			$value
		);

		return str_replace("\0DOLLAR\0", '$', $value);
	}
}
