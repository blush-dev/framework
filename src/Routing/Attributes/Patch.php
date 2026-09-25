<?php

/**
 * Patch route attribute.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Routing\Attributes;

use Attribute;

/**
 * Declares a `PATCH` route. See `Route`.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class Patch extends Route
{
	/**
	 * @param array<string, string>                     $where
	 * @param array<string, string|int|float|bool|null> $defaults
	 * @param list<class-string>                        $middleware
	 */
	public function __construct(
		string $path,
		?string $name = null,
		array $where = [],
		array $defaults = [],
		array $middleware = []
	) {
		parent::__construct($path, ['PATCH'], $name, $where, $defaults, $middleware);
	}
}
