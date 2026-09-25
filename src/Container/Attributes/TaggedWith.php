<?php

/**
 * Tagged with attribute.
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
 * Injects every service assigned to a container tag and with an assigned
 * tagged attribute value into the attributed parameter, mirroring
 * `Container::taggedWith()`. Pair it with an `iterable` or `array` parameter type:
 *
 *     public function __construct(
 *         #[TaggedWith('channels', 'slug')] iterable $channels
 *     ) {}
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class TaggedWith implements ContextualAttribute
{
	/**
	 * Stores the tag and attributes to resolve.
	 */
	public function __construct(
		private readonly string $tag,
		private readonly string $attribute
	) {}

	/**
	 * Returns a map from a chosen attribute's value to its abstract, for
	 * every member of `$tag` that was given that attribute.
	 *
	 * @return array<array-key, object>
	 */
	#[Override]
	public function resolve(Container $container): array
	{
		return $container->taggedWith($this->tag, $this->attribute);
	}
}
