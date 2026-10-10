<?php

/**
 * Reddit provider.
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
 * Reddit (D-692): posts on `reddit.com` (with `www.`, `old.`, and
 * `new.`), a rich embed (D-691). Its oEmbed answer is a quote of the
 * post's title, author, and community; the script that draws the post is
 * core's `blush/embed-reddit` asset. Nothing is framed. Share links
 * (`/r/{name}/s/{id}`) only redirect, so they're links.
 */
final class Reddit extends EmbedProvider
{
	public function __construct()
	{
		$schemes = [];

		foreach (['reddit.com', 'www.reddit.com', 'old.reddit.com', 'new.reddit.com'] as $host) {
			$schemes[] = "https://{$host}/r/*/comments/*";
		}

		parent::__construct('reddit', 'Reddit', $schemes, 'https://www.reddit.com/oembed');
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
		return AssetRegistrar::EMBED_REDDIT;
	}
}
