<?php

/**
 * Media prop attribute.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View\Component;

use Attribute;

/**
 * Marks a component's constructor parameter as a media reference (D-179),
 * so it's a `media` field in its definition. In Markdown, the reference
 * is resolved like an image's before the component gets it: a file next
 * to the entry (`clip.mp4`, in a page bundle) or in the media folder
 * becomes its URL.
 *
 * ```php
 * public function __construct(#[MediaProp] public readonly string $src = '') {}
 * ```
 *
 * A promoted parameter's attributes also land on its property, hence
 * both targets.
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final readonly class MediaProp
{
}
