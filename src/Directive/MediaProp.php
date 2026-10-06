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

namespace Blush\Directive;

use Attribute;
use Blush\Media\MediaKind;

/**
 * Marks a directive's constructor parameter as a media reference (D-179),
 * so it's a `media` field in its definition. In Markdown, the reference
 * is resolved like an image's before the directive gets it: a file in
 * the media folder becomes its URL.
 *
 * ```php
 * public function __construct(#[MediaProp] public readonly string $src = '') {}
 * ```
 *
 * A promoted parameter's attributes also land on its property, hence
 * both targets. A `kind` says which kind of file it plays (D-314), so the
 * admin's picker offers only those: `#[MediaProp(MediaKind::Video)]`.
 * A field that takes any file (a download) names none.
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final readonly class MediaProp
{
	public function __construct(public ?MediaKind $kind = null)
	{}
}
