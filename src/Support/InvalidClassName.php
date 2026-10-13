<?php

/**
 * Invalid class name exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Support;

use InvalidArgumentException;
use Blush\Core\BlushException;

/**
 * Thrown when a BEM block, element, or modifier name (`Bem`) isn't one a
 * class can be made of.
 */
final class InvalidClassName extends InvalidArgumentException implements BlushException
{
	/**
	 * A name that isn't letters, digits, hyphens, and underscores.
	 *
	 * @param string $part `block`, `element`, or `modifier`.
	 */
	public static function for(string $part, string $name): self
	{
		return new self(sprintf(
			'"%s" isn\'t a valid BEM %s name: use letters, digits, hyphens, and underscores, starting with a letter or digit.',
			$name,
			$part
		));
	}
}
