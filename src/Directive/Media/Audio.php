<?php

/**
 * Audio directive.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Directive\Media;

use Override;
use Blush\Directive\Directive;
use Blush\Directive\DirectiveContent;
use Blush\Directive\DirectiveView;
use Blush\Directive\MediaProp;
use Blush\Core\Framework;
use Blush\Media\MediaKind;
use Blush\Directive\DirectiveKind;

/**
 * Plays an audio file with the browser's controls (D-175, D-179):
 * `::audio[A caption]{src=episode.mp3}`. `src` is resolved like an
 * image's (a file next to the entry, or in the media folder), and the
 * label is the caption. `preload` is `metadata` by default; `loop`
 * repeats it.
 */
final class Audio extends Directive
{
	/**
	 * @inheritDoc
	 */
	public const DirectiveContent CONTENT = DirectiveContent::Text;

	/**
	 * @inheritDoc
	 */
	public const ?DirectiveKind KIND = DirectiveKind::Leaf;

	public function __construct(
		#[MediaProp(MediaKind::Audio)] public readonly string $src = '',
		public readonly MediaPreload $preload = MediaPreload::Metadata,
		public readonly bool $loop = false,
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

	/**
	 * Returns the `<audio>` element's attributes, escaped: its class
	 * (`directive-audio__player`), `src`, the browser's controls,
	 * `preload`, and `loop`.
	 */
	public function playerAttributes(): string
	{
		return self::html([
			'class'    => $this->block() . '__player',
			'src'      => $this->src,
			'controls' => true,
			'preload'  => $this->preload->value,
			'loop'     => $this->loop
		]);
	}

	/**
	 * Renders the framework's template for it, `resources/directives/audio.php`
	 * (D-382), when the theme chain has none of its own.
	 */
	#[Override]
	public function render(): DirectiveView
	{
		return $this->view(Framework::path('resources/directives/audio.php'));
	}
}
