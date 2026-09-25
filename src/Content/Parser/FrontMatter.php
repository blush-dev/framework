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
