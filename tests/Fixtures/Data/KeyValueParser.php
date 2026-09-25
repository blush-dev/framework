<?php

/**
 * NEON-like data parser fixture.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Data;

use Override;
use Blush\Data\DataParser;

/**
 * Parses `key = value` lines, standing in for a format an extension adds.
 */
final class KeyValueParser implements DataParser
{
	#[Override]
	public function parse(string $contents): array
	{
		$data = [];

		foreach (preg_split('/\R/', trim($contents)) ?: [] as $line) {
			[$key, $value] = array_map(trim(...), explode('=', $line, 2)) + [1 => ''];
			$data[$key]    = $value;
		}

		return $data;
	}
}
