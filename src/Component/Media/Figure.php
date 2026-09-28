<?php

/**
 * Figure component.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Component\Media;

use Override;
use Blush\Component\Component;
use Blush\Component\ComponentContent;
use Blush\Component\MediaProp;

/**
 * An image with a caption (the label) (D-113, D-195):
 * `::figure[A caption]{src=photo.jpg alt="Describe the photo"}`. `src`
 * is resolved like an image's; without it, nothing renders.
 */
final class Figure extends Component
{
	/**
	 * @inheritDoc
	 */
	public const ComponentContent CONTENT = ComponentContent::Text;

	public function __construct(
		#[MediaProp] public readonly string $src = '',
		public readonly string $alt = '',
		public readonly string $label = ''
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function shouldRender(): bool
	{
		return $this->src !== '';
	}

	/**
	 * Returns the caption, as HTML: the content, else the label escaped.
	 */
	public function caption(): string
	{
		return $this->contentOr($this->label);
	}
}
