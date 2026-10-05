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
use Blush\Component\Component;
use Blush\Component\ComponentContent;
use Blush\Component\ComponentView;
use Blush\Component\MediaProp;
use Blush\Core\AppConfig;
use Blush\Core\Framework;
use Blush\Media\MediaKind;
use Blush\Media\MediaResolver;

/**
 * Plays a video file with the browser's controls (D-175, D-179):
 * `::video[A caption]{src=clip.mp4 poster=clip.jpg track=clip.vtt}`.
 * `src`, `poster` (an image shown before it plays), and `track` (a WebVTT
 * captions file, in the page's language) are resolved like an image's,
 * and the label is the caption (D-198).
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

	// phpcs:disable -- PHPCS 4.0 doesn't tokenize property hooks yet.
	/**
	 * The track's language: the page locale's language.
	 */
	public string $trackLang {
		get => Locale::getPrimaryLanguage($this->locale($this->app->locale)) ?? 'en';
	}
	// phpcs:enable

	public function __construct(
		MediaResolver $media,
		private readonly AppConfig $app,
		#[MediaProp(MediaKind::Video)] public readonly string $src = '',
		#[MediaProp(MediaKind::Image)] public readonly string $poster = '',
		#[MediaProp] public readonly string $track = '',
		?int $width = null,
		?int $height = null,
		public readonly MediaPreload $preload = MediaPreload::Metadata,
		public readonly bool $loop = false,
		public readonly bool $muted = false,
		public readonly string $label = ''
	) {
		$image = $width === null && $height === null && $poster !== '' ? $media->resolve($poster) : null;

		$this->width  = $width ?? $image?->width;
		$this->height = $height ?? $image?->height;
	}

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

	/**
	 * Returns the `<video>` element's attributes, escaped: its class
	 * (`component-video__player`), `src`, the browser's controls, inline
	 * playback on phones, `preload`, `poster`, its size (when known),
	 * `loop`, and `muted`.
	 */
	public function playerAttributes(): string
	{
		return self::html([
			'class'       => $this->block() . '__player',
			'src'         => $this->src,
			'controls'    => true,
			'playsinline' => true,
			'preload'     => $this->preload->value,
			'poster'      => $this->poster,
			'width'       => $this->width,
			'height'      => $this->height,
			'loop'        => $this->loop,
			'muted'       => $this->muted
		]);
	}

	/**
	 * Returns the `<track>` element's attributes, escaped: its class
	 * (`component-video__track`), `kind="captions"`, `src`, the page's
	 * language, and the theme's `media.captions` text as its label. It's
	 * on by default.
	 */
	public function trackAttributes(): string
	{
		return self::html([
			'class'   => $this->block() . '__track',
			'kind'    => 'captions',
			'src'     => $this->track,
			'srclang' => $this->trackLang,
			'label'   => $this->t('media.captions'),
			'default' => true
		]);
	}

	/**
	 * Renders the framework's template for it, `resources/components/video.php`
	 * (D-382), when the theme chain has none of its own.
	 */
	#[Override]
	public function render(): ComponentView
	{
		return $this->view(Framework::path('resources/components/video.php'));
	}
}
