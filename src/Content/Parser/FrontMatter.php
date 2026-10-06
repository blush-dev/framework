<?php

/**
 * Front matter.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Parser;

use Blush\Data\InvalidData;
use Blush\Data\YamlParser;

/**
 * Splits YAML front matter off the top of a document and parses it. Front
 * matter starts on the first line with `---` and ends at the next line that
 * is `---` (or YAML's `...`). A document without a closed block has no
 * front matter, as in 1.x, and a leading byte-order mark is ignored.
 */
final readonly class FrontMatter
{
	/**
	 * Matches the front matter block. Group 1 is the YAML, when there is
	 * any.
	 */
	private const string PATTERN = '/\A(?:\xEF\xBB\xBF)?---[ \t]*\R(?:(.*?)\R)?(?:---|\.\.\.)[ \t]*(?:\R|\z)/s';

	public function __construct(private YamlParser $yaml)
	{}

	/**
	 * Returns the front matter's YAML (or `null` when there's no block)
	 * and the body after it.
	 *
	 * @return array{?string, string}
	 */
	public static function split(string $contents): array
	{
		if (preg_match(self::PATTERN, $contents, $matches) !== 1) {
			return [null, str_starts_with($contents, "\xEF\xBB\xBF") ? substr($contents, 3) : $contents];
		}

		return [$matches[1] ?? '', substr($contents, strlen($matches[0]))];
	}

	/**
	 * Returns a top-level key's value as the front matter writes it, on
	 * its own line (`date     : 2007-00-00 23:22:00 -5` is `2007-00-00
	 * 23:22:00 -5`), without quotes around it, or `null` when no line
	 * sets it. YAML hands dates over already read (a `2007-00-00`
	 * placeholder rolled over to 2006-11-30), so checks of dates as
	 * written read them here.
	 */
	public static function written(string $contents, string $key): ?string
	{
		[$yaml] = self::split($contents);

		if ($yaml === null || preg_match('/^' . preg_quote($key, '/') . '[ \t]*:[ \t]*(.*?)[ \t]*$/m', $yaml, $line) !== 1) {
			return null;
		}

		return preg_match('/^([\'"])(.*)\1$/', $line[1], $quoted) === 1 ? $quoted[2] : $line[1];
	}

	/**
	 * Parses a document's front matter and returns it with the body.
	 *
	 * @return array{array<array-key, mixed>, string}
	 * @throws InvalidDocument
	 */
	public function parse(string $contents): array
	{
		[$yaml, $body] = self::split($contents);

		if ($yaml === null || trim($yaml) === '') {
			return [[], $body];
		}

		try {
			$data = $this->yaml->parse($yaml);
		} catch (InvalidData $e) {
			throw new InvalidDocument(sprintf('Invalid front matter: %s', $e->getMessage()), previous: $e);
		}

		if ($data === null) {
			return [[], $body];
		}

		if (! is_array($data) || ($data !== [] && array_is_list($data))) {
			throw new InvalidDocument('Front matter must be a map of keys to values.');
		}

		return [$data, $body];
	}
}
