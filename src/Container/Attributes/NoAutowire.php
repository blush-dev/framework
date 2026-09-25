<?php

/**
 * NoAutowire attribute.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Container\Attributes;

use Attribute;

/**
 * Suppresses type-based autowiring for the attributed parameter. Instead of
 * resolving the parameter from its type, the container resolves it as if it
 * were not involved: the parameter's declared default value is used, or `null`
 * when the parameter is nullable but declares no default. A required parameter
 * with no fallback of its own still fails, since there is nothing to inject.
 *
 * This is useful when a parameter's type is autowirable but an instance is not
 * wanted by default — a value object the caller supplies later, for example:
 *
 *     public function __construct(
 *         #[NoAutowire] ?User $user = null
 *     ) {}
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class NoAutowire
{
}
