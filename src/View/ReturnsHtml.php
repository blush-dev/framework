<?php

/**
 * Returns HTML attribute.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View;

use Attribute;

/**
 * Marks a `Template` method whose result is rendered HTML to print as it
 * is (D-502): a section, a partial, a component, a region. A view engine
 * that escapes on its own reads it to mark the method safe (Twig's
 * `is_safe`) instead of keeping its own list.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class ReturnsHtml
{
}
