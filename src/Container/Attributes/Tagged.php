<?php

/**
 * Tagged attribute.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Container\Attributes;

use Attribute;
use Override;
use Blush\Container\Container;

/**
 * Injects every service assigned to a container tag into the attributed
 * parameter, mirroring `Container::tagged()`. Pair it with an `iterable` or
 * `array` parameter type:
 *
 *     public function __construct(
 *         #[Tagged('theme.blocks')] iterable $blocks
 *     ) {}
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class Tagged implements ContextualAttribute
{
	/**
	 * Stores the tag whose assigned services are injected.
	 */
	public function __construct(private readonly string $tag)
	{
	}

	/**
	 * Resolves to the array of services assigned to the tag.
	 *
	 * @return array<object>
	 */
	#[Override]
	public function resolve(Container $container): array
	{
		return $container->tagged($this->tag);
	}
}
