<?php

/**
 * Link prop attribute.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Component;

use Attribute;

/**
 * Marks a component's constructor parameter as a link (D-190), such as a
 * button's `url`. In Markdown, a link starting with `/` becomes a full
 * URL on the site, as Markdown's own links do, so it works in feeds.
 * (A media reference uses `#[MediaProp]`, which does the same after
 * finding the file.)
 *
 * ```php
 * public function __construct(#[LinkProp] public readonly string $url = '') {}
 * ```
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final readonly class LinkProp
{
}
