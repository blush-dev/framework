<?php

/**
 * Get attribute.
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
 * Resolves the attributed parameter from the container by identifier, mirroring
 * `Container::get()`. The identifier may be a class name or any string key the
 * container can resolve:
 *
 *     public function __construct(
 *         #[Get('app.paths')]      array $paths,
 *         #[Get(FileCache::class)] Cache $cache
 *     ) {}
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class Get implements ContextualAttribute
{
	/**
	 * Stores the identifier to resolve from the container.
	 */
	public function __construct(private readonly string $abstract)
	{
	}

	/**
	 * Resolves the identifier from the container.
	 */
	#[Override]
	public function resolve(Container $container): mixed
	{
		return $container->get($this->abstract);
	}
}
