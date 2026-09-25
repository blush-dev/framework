<?php

/**
 * Param attribute.
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
use Blush\Container\NotFoundException;

/**
 * Marks a constructor parameter as resolved from a container-backed named
 * parameter (set via `Container::setParam()`) rather than autowired from its
 * type. Requires an explicit attribute on the parameter, so a value is never
 * injected just because the parameter name happens to match:
 *
 *     public function __construct(
 *         #[Param('apiKey')]  private string $apiKey,
 *         #[Param('timeout')] private int    $timeout = 30
 *     ) {}
 *
 * If the named parameter was never set, resolution defers to the
 * constructor parameter's own fallback — its default value, or `null` when
 * it's nullable — the same way an unsatisfiable autowired type does. This
 * is why `resolve()` throws `NotFoundException` specifically rather than
 * returning `null` or a general `ContainerException`: the container's
 * parameter-resolution loop catches that particular exception to know a
 * fallback should be tried before the failure is treated as fatal.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class Param implements ContextualAttribute
{
	/**
	 * Defines the container parameter name to resolve the attributed
	 * constructor parameter's value from, as previously set via
	 * `Container::setParam()`.
	 */
	public function __construct(private readonly string $name)
	{
	}

	/**
	 * Looks up the named parameter on the resolving container. The lookup
	 * itself, including the not-set case, is delegated entirely to
	 * `Container::getParam()`, which throws `NotFoundException` when
	 * `$name` has no value set.
	 *
	 * @throws NotFoundException When no value has been set for `$name`.
	 */
	#[Override]
	public function resolve(Container $container): mixed
	{
		return $container->getParam($this->name);
	}
}
