<?php

/**
 * Flickr provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Embed\Providers;

use Override;
use Blush\Embed\EmbedData;
use Blush\Embed\EmbedProvider;
use Blush\Embed\EmbedType;

/**
 * Flickr (D-635): photo pages and `flic.kr` short links, shown as the
 * photo oEmbed answers with (only from Flickr's own image hosts),
 * linked to its page with its credit. Nothing is framed: albums and
 * videos, which Flickr answers with its script, are links.
 */
final class Flickr extends EmbedProvider
{
	public function __construct()
	{
		parent::__construct(
			'flickr',
			'Flickr',
			['https://www.flickr.com/photos/*', 'https://flickr.com/photos/*', 'https://flic.kr/p/*'],
			'https://www.flickr.com/services/oembed/'
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function frame(string $url, ?EmbedData $data): ?string
	{
		return null;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function photo(string $url, ?EmbedData $data): ?string
	{
		if ($data?->type !== EmbedType::Photo || $data->url === null) {
			return null;
		}

		return self::link($data->url, ['*.staticflickr.com'], '#^/[0-9/]+_[0-9a-f]+(?:_[a-z0-9]+)?\.(?:jpe?g|png|gif)$#i') === null ? null : $data->url;
	}
}
