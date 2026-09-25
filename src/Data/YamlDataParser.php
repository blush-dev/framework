<?php

/**
 * YAML data parser.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Data;

use Override;

/**
 * Parses YAML data files through the `YamlParser`.
 */
final readonly class YamlDataParser implements DataParser
{
	public function __construct(private YamlParser $yaml)
	{}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function parse(string $contents): array
	{
		$data = $this->yaml->parse($contents);

		if ($data === null) {
			return [];
		}

		if (! is_array($data)) {
			throw new InvalidData('A YAML data file must hold a map or a list.');
		}

		return $data;
	}
}
