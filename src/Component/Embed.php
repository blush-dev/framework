<?php

/**
 * Embed component.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Component;

use Override;
use Blush\Embed\Embeds;
use Blush\View\Escaper;

/**
 * Embeds a page by its URL (D-113, D-184):
 * `::embed[Caption]{url="https://youtu.be/…"}`. Only URLs a registered
 * provider matches are framed (YouTube and Vimeo are built in, and sites
 * add more), so content never frames an unknown site; any other URL, or
 * one its provider can't frame, renders as a link.
 *
 * The provider is asked about the URL over oEmbed (cached), for the
 * embed's real size and title. The label is the caption. The frame's
 * accessible name (`frameTitle()`) is `title`, else the provider's title
 * for it, else the label, else the theme's `embed.title` text.
 *
 * Its template prints the frame's wrapper with `wrapperAttributes()` (the
 * aspect ratio) and the frame, the `<iframe>`, with `frameAttributes()`:
 * its source, size, and name, loaded lazily, with no more than the
 * provider's origin as the referrer.
 */
final class Embed extends Component
{
	/**
	 * @inheritDoc
	 */
	public const ComponentContent CONTENT = ComponentContent::Text;

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
	 * The provider's title for the embed, or `''`.
	 */
	public readonly string $embedTitle;

	/**
	 * The provider's thumbnail URL, or `null`.
	 */
	public readonly ?string $thumbnail;

	public function __construct(
		Embeds $embeds,
		public readonly string $url = '',
		public readonly string $title = '',
		public readonly string $label = ''
	) {
		$provider = $url === '' ? null : $embeds->provider($url);
		$data     = $provider === null ? null : $embeds->lookup($provider, $url);
		$sized    = $data?->width !== null && $data->height !== null;

		$this->provider      = $provider === null ? '' : $provider->name;
		$this->providerLabel = $provider === null ? '' : $provider->label;
		$this->src           = $provider?->frame($url, $data);
		$this->width         = $sized ? $data->width : null;
		$this->height        = $sized ? $data->height : null;
		$this->ratio         = $sized ? "{$data->width} / {$data->height}" : null;
		$this->portrait      = $sized && $data->height > $data->width;
		$this->embedTitle    = $data === null ? '' : $data->title;
		$this->thumbnail     = $data?->thumbnail;
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
	 * (`component-embed__wrapper`) and the embed's aspect ratio as
	 * `--embed-ratio`, when it's known.
	 */
	public function wrapperAttributes(): string
	{
		return self::html([
			'class' => $this->block() . '__wrapper',
			'style' => $this->ratio === null ? null : "--embed-ratio: {$this->ratio}"
		]);
	}

	/**
	 * Returns the frame's (the `<iframe>` element's) attributes, escaped:
	 * its class (`component-embed__frame`), `src`, its size (when known),
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
			'height'          => $sized ? $this->height : null,
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
		if (! $this->isFramed()) {
			return ['link'];
		}

		return $this->portrait ? [$this->provider, 'portrait'] : [$this->provider];
	}
}
