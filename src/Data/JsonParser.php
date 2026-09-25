<?php

/**
 * JSON data parser.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Data;

use JsonException;
use Override;

/**
 * Parses JSON data files. Objects become associative arrays.
 */
final readonly class JsonParser implements DataParser
{
	/**
	 * @inheritDoc
	 */
	#[Override]
	public function parse(string $contents): array
	{
		if (trim($contents) === '') {
			return [];
		}

		try {
			$data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			throw new InvalidData(sprintf('Invalid JSON: %s', $e->getMessage()), previous: $e);
		}

		if (! is_array($data)) {
			throw new InvalidData('A JSON data file must hold an object or an array.');
		}

		return $data;
	}
}
