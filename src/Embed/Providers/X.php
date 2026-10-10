<?php

/**
 * X provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Embed\Providers;

use Override;
use Blush\Asset\AssetRegistrar;
use Blush\Embed\EmbedData;
use Blush\Embed\EmbedProvider;

/**
 * X (D-691): posts on `x.com` and `twitter.com`, a rich embed. Its
 * oEmbed answer is the post as a quote, asked for without its script
 * and with `dnt` (so X doesn't tailor what it shows to the reader); the
 * script that draws the post is core's `blush/embed-x` asset. Nothing
 * is framed, and links to anything but a post are links.
 */
final class X extends EmbedProvider
{
	public function __construct()
	{
		$schemes = [];

		foreach (['x.com', 'www.x.com', 'mobile.x.com', 'twitter.com', 'www.twitter.com', 'mobile.twitter.com'] as $host) {
			$schemes[] = "https://{$host}/*/status/*";
		}

		parent::__construct('x', 'X', $schemes, 'https://publish.x.com/oembed');
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function request(string $url): string
	{
		return parent::request($url) . '&' . http_build_query(['omit_script' => 1, 'dnt' => 'true']);
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
	public function asset(): string
	{
		return AssetRegistrar::EMBED_X;
	}
}
