<?php

/**
 * TikTok provider.
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

/**
 * TikTok (D-635): video links, framed by TikTok's player rather than
 * the script its oEmbed answers with (as YouTube is framed from its
 * no-cookie host), without related videos at the end. It's portrait,
 * 9:16, as TikTok's answer gives no size. oEmbed is asked for the
 * title. Short `vm.tiktok.com` links only redirect, so they're links.
 */
final class TikTok extends EmbedProvider
{
	public function __construct()
	{
		parent::__construct(
			'tiktok',
			'TikTok',
			['https://www.tiktok.com/@*/video/*', 'https://tiktok.com/@*/video/*', 'https://m.tiktok.com/@*/video/*', 'https://www.tiktok.com/embed/*', 'https://www.tiktok.com/player/v1/*'],
			'https://www.tiktok.com/oembed'
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function frame(string $url, ?EmbedData $data): ?string
	{
		[$id] = self::link($url, ['www.tiktok.com', 'tiktok.com', 'm.tiktok.com'], '#^/(?:@[a-z0-9._]+/video|embed(?:/v2)?|player/v1)/(\d+)/?$#i') ?? [null];

		return $id === null ? null : "https://www.tiktok.com/player/v1/{$id}?rel=0";
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function size(string $url, ?EmbedData $data): array
	{
		return [324, 576];
	}
}
