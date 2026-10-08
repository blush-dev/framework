<?php

/**
 * Embed directive.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Directive;

use Override;
use Blush\Core\Framework;
use Blush\Embed\Embeds;
use Blush\View\Escaper;

/**
 * Embeds a page by its URL (D-113, D-184):
 * `::embed[Caption]{url="https://youtu.be/…"}`. Only URLs a registered
 * provider matches are framed (YouTube, Vimeo, TED, CodePen, Spotify,
 * and SoundCloud are built in, and sites add more), so content never
 * frames an unknown site; any other URL, or one its provider can't
 * frame, renders as a link.
 *
 * The provider is asked about the URL over oEmbed (cached), for the
 * embed's real size and title. The label is the caption. The frame's
 * accessible name (`frameTitle()`) is `title`, else the provider's title
 * for it, else the label, else the theme's `embed.title` text.
 *
 * A provider that answers with a photo (Flickr, D-635) shows it as an
 * image linked to its page, with `alt` as its alt text (else the
 * title) and a credit after the caption, with the `photo` modifier.
 *
 * Its template prints the frame's wrapper with `wrapperAttributes()` (the
 * aspect ratio, or the height of a player that fills its column at a
 * set height, such as Spotify's, with the `fixed` modifier; D-634) and
 * the frame, the `<iframe>`, with `frameAttributes()`:
 * its source, size, and name, loaded lazily, with no more than the
 * provider's origin as the referrer.
 */
final class Embed extends Directive
{
	/**
	 * @inheritDoc
	 */
	public const DirectiveContent CONTENT = DirectiveContent::Text;

	/**
	 * @inheritDoc
	 */
	public const ?DirectiveKind KIND = DirectiveKind::Leaf;

	/**
	 * The provider's name, or `''`.
	 */
	public readonly string $provider;

	/**
	 * The provider's display name, or `''`.
	 */
	public readonly string $providerLabel;

	/**
	 * The frame URL, or `null` when the URL renders as a link.
	 */
	public readonly ?string $src;

	/**
	 * The embed's width, from its provider.
	 */
	public readonly ?int $width;

	/**
	 * The embed's height, from its provider.
	 */
	public readonly ?int $height;

	/**
	 * The embed's aspect ratio for CSS (`560 / 315`), or `null`.
	 */
	public readonly ?string $ratio;

	/**
	 * Whether the embed is taller than it is wide (such as a 9:16 short).
	 */
	public readonly bool $portrait;

	/**
	 * Whether the frame fills its column at a fixed height (`$height`),
	 * as an audio player does, rather than keeping a shape (D-634).
	 */
	public readonly bool $fixed;

	/**
	 * The provider's title for the embed, or `''`.
	 */
	public readonly string $embedTitle;

	/**
	 * The provider's thumbnail URL, or `null`.
	 */
	public readonly ?string $thumbnail;

	/**
	 * The image a photo embed shows (D-635), or `null` for a frame or a
	 * link.
	 */
	public readonly ?string $photo;

	/**
	 * Who made the embed, from its provider, or `''`.
	 */
	public readonly string $author;

	public function __construct(
		Embeds $embeds,
		public readonly string $url = '',
		public readonly string $title = '',
		public readonly string $alt = '',
		public readonly string $label = ''
	) {
		$provider = $url === '' ? null : $embeds->provider($url);
		$data     = $provider === null ? null : $embeds->lookup($provider, $url);
		$src      = $provider?->frame($url, $data);
		$fixed    = $src === null ? null : $provider->fixedHeight($url, $data);
		$size     = $fixed === null ? $provider?->size($url, $data) : null;

		[$width, $height] = $size ?? [null, null];

		$this->provider      = $provider === null ? '' : $provider->name;
		$this->providerLabel = $provider === null ? '' : $provider->label;
		$this->src           = $src;
		$this->photo         = $src === null ? $provider?->photo($url, $data) : null;
		$this->fixed         = $fixed !== null;
		$this->width         = $width;
		$this->height        = $height ?? $fixed;
		$this->ratio         = $size === null ? null : "{$width} / {$height}";
		$this->portrait      = $size !== null && $height > $width;
		$this->embedTitle    = $data === null ? '' : $data->title;
		$this->thumbnail     = $data?->thumbnail;
		$this->author        = $data === null ? '' : $data->author;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function shouldRender(): bool
	{
		return $this->url !== '';
	}

	/**
	 * Returns whether the URL is framed, rather than shown as a link.
	 */
	public function isFramed(): bool
	{
		return $this->src !== null;
	}

	/**
	 * Returns whether the URL is shown as its photo (D-635).
	 */
	public function isPhoto(): bool
	{
		return $this->photo !== null;
	}

	/**
	 * Returns a photo's alt text: `alt`, else `title`, else the
	 * provider's title, else `''`.
	 */
	public function altText(): string
	{
		return match (true) {
			trim($this->alt) !== ''  => trim($this->alt),
			$this->title !== ''      => $this->title,
			default                  => $this->embedTitle
		};
	}

	/**
	 * Returns a photo's credit, as text: "Photo by {author} on
	 * {provider}" (the theme's `embed.credit`), or `''` when the
	 * provider doesn't say who made it.
	 */
	public function credit(): string
	{
		return $this->isPhoto() && $this->author !== '' ? $this->t('embed.credit', author: $this->author, provider: $this->providerLabel) : '';
	}

	/**
	 * Returns a photo's (the `<img>` element's) attributes, escaped: its
	 * class (`directive-embed__photo`), `src`, size (when known), lazy
	 * loading, and a referrer policy that sends only the site's origin.
	 * Its `alt` is the template's to print (`altText()`), as an empty
	 * one is still printed.
	 */
	public function photoAttributes(): string
	{
		return self::html([
			'class'          => $this->block() . '__photo',
			'src'            => $this->photo,
			'width'          => $this->width,
			'height'         => $this->width === null ? null : $this->height,
			'loading'        => 'lazy',
			'decoding'       => 'async',
			'referrerpolicy' => 'strict-origin-when-cross-origin'
		]);
	}

	/**
	 * Returns the frame's accessible name: `title`, else the provider's
	 * title, else the label, else the theme's `embed.title` text.
	 */
	public function frameTitle(): string
	{
		return match (true) {
			$this->title !== ''      => $this->title,
			$this->embedTitle !== '' => $this->embedTitle,
			trim($this->label) !== '' => trim($this->label),
			default                  => $this->t('embed.title', provider: $this->providerLabel)
		};
	}

	/**
	 * Returns the caption, as HTML: the content, else the label escaped.
	 */
	public function caption(): string
	{
		return $this->contentOr($this->label);
	}

	/**
	 * Returns the link's text for a URL that isn't framed, as HTML: the
	 * caption, else `title`, else the provider's title, else the URL.
	 */
	public function linkText(): string
	{
		return match (true) {
			$this->caption() !== ''  => $this->caption(),
			$this->title !== ''      => Escaper::html($this->title),
			$this->embedTitle !== '' => Escaper::html($this->embedTitle),
			default                  => Escaper::html($this->url)
		};
	}

	/**
	 * Returns the frame wrapper's attributes, escaped: its class
	 * (`directive-embed__wrapper`) and the embed's aspect ratio as
	 * `--embed-ratio`, when it's known, or a fixed frame's height as
	 * `--embed-height` (D-634).
	 */
	public function wrapperAttributes(): string
	{
		return self::html([
			'class' => $this->block() . '__wrapper',
			'style' => match (true) {
				$this->fixed           => "--embed-height: {$this->height}px",
				$this->ratio !== null  => "--embed-ratio: {$this->ratio}",
				default                => null
			}
		]);
	}

	/**
	 * Returns the frame's (the `<iframe>` element's) attributes, escaped:
	 * its class (`directive-embed__frame`), `src`, its size (when known;
	 * a fixed frame's height alone),
	 * its accessible name (`frameTitle()`), lazy loading, the features a
	 * video player needs, and a referrer policy that sends only the site's
	 * origin.
	 */
	public function frameAttributes(): string
	{
		$sized = $this->width !== null && $this->height !== null;

		return self::html([
			'class'           => $this->block() . '__frame',
			'src'             => $this->src,
			'width'           => $sized ? $this->width : null,
			'height'          => $sized || $this->fixed ? $this->height : null,
			'title'           => $this->frameTitle(),
			'loading'         => 'lazy',
			'allow'           => 'autoplay; encrypted-media; picture-in-picture; fullscreen',
			'allowfullscreen' => true,
			'referrerpolicy'  => 'strict-origin-when-cross-origin'
		]);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function modifiers(): array
	{
		if ($this->isPhoto()) {
			return [$this->provider, 'photo'];
		}

		if (! $this->isFramed()) {
			return ['link'];
		}

		return match (true) {
			$this->fixed    => [$this->provider, 'fixed'],
			$this->portrait => [$this->provider, 'portrait'],
			default         => [$this->provider]
		};
	}

	/**
	 * Renders the framework's template for it, `resources/directives/embed.php`
	 * (D-382), when the theme chain has none of its own.
	 */
	#[Override]
	public function render(): DirectiveView
	{
		return $this->view(Framework::path('resources/directives/embed.php'));
	}
}
