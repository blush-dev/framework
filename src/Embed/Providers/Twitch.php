<?php

/**
 * Twitch provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Embed\Providers;

use Override;
use Blush\Core\AppConfig;
use Blush\Embed\EmbedData;
use Blush\Embed\EmbedProvider;

/**
 * Twitch (D-635): channels (their live stream), past videos, and clips,
 * framed by Twitch's players, which don't play automatically. Twitch
 * has no oEmbed, so it isn't asked; the frame is named by the embed's
 * title or label. Its players only play on the page named as their
 * `parent`, the site's host (from `APP_URL`), and want HTTPS there;
 * without a host, links are links.
 */
final class Twitch extends EmbedProvider
{
	/**
	 * Twitch's own pages, which aren't channels.
	 */
	private const array PAGES = ['directory', 'downloads', 'drops', 'friends', 'inventory', 'jobs', 'messages', 'p', 'prime', 'search', 'settings', 'store', 'subscriptions', 'turbo', 'videos', 'wallet'];

	private const array HOSTS = ['www.twitch.tv', 'twitch.tv', 'm.twitch.tv'];

	public function __construct(private readonly AppConfig $app)
	{
		parent::__construct(
			'twitch',
			'Twitch',
			['https://www.twitch.tv/*', 'https://twitch.tv/*', 'https://m.twitch.tv/*', 'https://clips.twitch.tv/*'],
			null
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function frame(string $url, ?EmbedData $data): ?string
	{
		$parent = strtolower((string) parse_url($this->app->url, PHP_URL_HOST));

		if ($parent === '') {
			return null;
		}

		$clip    = self::link($url, ['clips.twitch.tv'], '#^/([a-z0-9_-]+)/?$#i') ?? self::link($url, self::HOSTS, '#^/[a-z0-9_]+/clip/([a-z0-9_-]+)/?$#i');
		$video   = self::link($url, self::HOSTS, '#^/videos/(\d+)/?$#');
		$channel = self::link($url, self::HOSTS, '#^/([a-z0-9_]{1,25})/?$#i');

		return match (true) {
			$clip !== null    => 'https://clips.twitch.tv/embed?' . http_build_query(['clip' => $clip[0], 'parent' => $parent, 'autoplay' => 'false']),
			$video !== null   => 'https://player.twitch.tv/?' . http_build_query(['video' => "v{$video[0]}", 'parent' => $parent, 'autoplay' => 'false']),
			$channel !== null && ! in_array(strtolower($channel[0]), self::PAGES, true)
				=> 'https://player.twitch.tv/?' . http_build_query(['channel' => strtolower($channel[0]), 'parent' => $parent, 'autoplay' => 'false']),
			default           => null
		};
	}
}
