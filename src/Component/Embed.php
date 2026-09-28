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

/**
 * Embeds a page by its URL (D-113, D-184):
 * `::embed[Caption]{url="https://youtu.be/…"}`. Only URLs a registered
 * provider matches are framed (YouTube and Vimeo are built in, and sites
 * add more), so content never frames an unknown site; any other URL, or
 * one its provider can't frame, renders as a link.
 *
 * The provider is asked about the URL over oEmbed (cached), for the
 * embed's real size and title. Its template gets `$url`, `$title`,
 * `$provider` (the provider's name, or `''`), `$providerLabel` (such as
 * `YouTube`), `$src` (the frame URL, or `null`), `$width` and `$height`
 * (or `null`), `$ratio` (such as `16 / 9`, or `null`), `$portrait`
 * (taller than wide), `$embedTitle`
 * (the provider's title for it, or `''`), and `$thumbnail`. The frame's
 * accessible name is `$title`, else `$embedTitle`, else the label, else
 * the theme's `embed.title` text.
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
		public readonly string $title = ''
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
}
