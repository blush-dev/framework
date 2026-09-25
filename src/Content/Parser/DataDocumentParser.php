<?php

/**
 * Data document parser.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Parser;

use Override;
use Blush\Data\DataLoader;
use Blush\Data\InvalidData;

/**
 * Parses data-only entries (`.json`, `.yaml`, `.yml`): the whole file is
 * front matter, and an optional `body` key holds a Markdown body. The data
 * format comes from the file extension `DocumentParsers` builds this parser
 * for, through the data parser registry, so any data format an extension
 * adds works for entries too.
 */
final readonly class DataDocumentParser implements DocumentParser
{
	/**
	 * The key holding a data entry's body.
	 */
	public const string BODY = 'body';

	public function __construct(
		private DataLoader $data,
		private string $extension = 'json'
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function parse(string $contents): Document
	{
		try {
			$data = $this->data->parser($this->extension)->parse($contents);
		} catch (InvalidData $e) {
			throw new InvalidDocument($e->getMessage(), previous: $e);
		}

		if ($data !== [] && array_is_list($data)) {
			throw new InvalidDocument('A data entry must be a map of keys to values.');
		}

		$body = $data[self::BODY] ?? '';
		unset($data[self::BODY]);

		if (! is_string($body)) {
			throw new InvalidDocument(sprintf('A data entry\'s "%s" must be text.', self::BODY));
		}

		return new Document($data, $body, BodyFormat::Markdown);
	}
}
