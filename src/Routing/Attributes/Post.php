<?php

/**
 * Post route attribute.
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
 * Declares a `POST` route. See `Route`.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class Post extends Route
{
	/**
	 * @param array<string, string>                     $where
	 * @param array<string, string|int|float|bool|null> $defaults
	 * @param list<class-string>                        $middleware
	 * @param bool                                      $exact      See `Route`.
	 */
	public function __construct(
		string $path,
		?string $name = null,
		array $where = [],
		array $defaults = [],
		array $middleware = [],
		bool $exact = false
	) {
		parent::__construct($path, ['POST'], $name, $where, $defaults, $middleware, $exact);
	}
}
