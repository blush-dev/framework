<?php

/**
 * Audio component.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View\Component\Media;

use Override;
use Blush\View\Component\Component;
use Blush\View\Component\ComponentContent;
use Blush\View\Component\MediaProp;

/**
 * Plays an audio file with the browser's controls (D-175, D-179):
 * `::audio[A caption]{src=episode.mp3}`. `src` is resolved like an
 * image's (a file next to the entry, or in the media folder), and the
 * label is the caption. `preload` is `metadata` by default; `loop`
 * repeats it.
 */
final class Audio extends Component
{
	/**
	 * @inheritDoc
	 */
	public const ComponentContent CONTENT = ComponentContent::Text;

	public function __construct(
		#[MediaProp] public readonly string $src = '',
		public readonly MediaPreload $preload = MediaPreload::Metadata,
		public readonly bool $loop = false
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function shouldRender(): bool
	{
		return $this->src !== '';
	}
}
