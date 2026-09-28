<?php

/**
 * Video component.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Component\Media;

use Locale;
use Override;
use Blush\Core\AppConfig;
use Blush\Media\MediaResolver;
use Blush\Component\Component;
use Blush\Component\ComponentContent;
use Blush\Component\MediaProp;

/**
 * Plays a video file with the browser's controls (D-175, D-179):
 * `::video[A caption]{src=clip.mp4 poster=clip.jpg captions=clip.vtt}`.
 * `src`, `poster` (an image shown before it plays), and `captions` (a
 * WebVTT track, in the site's language) are resolved like an image's, and
 * the label is the caption.
 *
 * Without `width` and `height`, the poster's size is used, so the page
 * doesn't shift while the video loads. `preload` is `metadata` by
 * default; `loop` repeats it and `muted` starts it silent.
 */
final class Video extends Component
{
	/**
	 * @inheritDoc
	 */
	public const ComponentContent CONTENT = ComponentContent::Text;

	/**
	 * The frame's width, given or from the poster.
	 */
	public readonly ?int $width;

	/**
	 * The frame's height, given or from the poster.
	 */
	public readonly ?int $height;

	/**
	 * The captions' language: the site locale's language.
	 */
	public readonly string $captionsLang;

	public function __construct(
		MediaResolver $media,
		AppConfig $app,
		#[MediaProp] public readonly string $src = '',
		#[MediaProp] public readonly string $poster = '',
		#[MediaProp] public readonly string $captions = '',
		?int $width = null,
		?int $height = null,
		public readonly MediaPreload $preload = MediaPreload::Metadata,
		public readonly bool $loop = false,
		public readonly bool $muted = false
	) {
		$image = $width === null && $height === null && $poster !== '' ? $media->resolve($poster) : null;

		$this->width        = $width ?? $image?->width;
		$this->height       = $height ?? $image?->height;
		$this->captionsLang = Locale::getPrimaryLanguage($app->locale) ?? 'en';
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function shouldRender(): bool
	{
		return $this->src !== '';
	}
}
