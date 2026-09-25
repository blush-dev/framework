<?php

/**
 * Singleton attribute.
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
 * Marks a class to be cached as a single shared instance when the container
 * autowires it, without requiring an explicit singleton binding. It only
 * applies to classes resolved without an explicit binding; a binding's declared
 * lifetime always takes precedence.
 *
 *     #[Singleton]
 *     final class FileCache implements Cache {}
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Singleton
{
}
