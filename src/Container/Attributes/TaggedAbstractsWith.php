<?php

/**
 * Tagged abstracts with attribute.
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
 * Injects a lookup map for a tagged group of services, keyed by one of the
 * attributes each member was tagged with, rather than the resolved services
 * themselves. This defers building any member until its abstract identifier
 * is looked up and passed to `make()` (or similar), which is what makes it
 * suited to a large tag group where a consumer only ever needs one member
 * at a time — e.g. resolving a single markup type by slug:
 *
 *     public function __construct(
 *         #[TaggedAbstractsWith('channel', 'slug')] private readonly array $channels
 *     ) {}
 *
 *     $instance = $this->container->make($this->channels[$slug]);
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class TaggedAbstractsWith implements ContextualAttribute
{
	/**
	 * Stores the tag to map and the attribute whose value keys the map.
	 */
	public function __construct(
		private readonly string $tag,
		private readonly string $attribute
	) {}

	/**
	 * Returns a map from a chosen attribute's value to its abstract, for
	 * every member of `$tag` that was given that attribute.
	 *
	 * @return array<mixed, string>
	 */
	#[Override]
	public function resolve(Container $container): array
	{
		return $container->taggedAbstractsWith($this->tag, $this->attribute);
	}
}
