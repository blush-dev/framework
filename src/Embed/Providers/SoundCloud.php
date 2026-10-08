<?php

/**
 * SoundCloud provider.
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
 * SoundCloud (D-634): track and playlist (set) links, private ones with
 * their secret part included, framed by SoundCloud's compact player (a
 * strip with the waveform, not the visual player oEmbed answers with):
 * 166 pixels for a track and 450 for a playlist, with its track list.
 * The answer's height is the visual player's, so it isn't used. The
 * player is given the plain link; oEmbed is asked about it, for the
 * title.
 */
final class SoundCloud extends EmbedProvider
{
	/**
	 * The heights of SoundCloud's compact player.
	 */
	private const int TRACK = 166;

	private const int SET = 450;

	/**
	 * Pages under an account that aren't a track.
	 */
	private const array PAGES = ['albums', 'comments', 'followers', 'following', 'likes', 'popular-tracks', 'reposts', 'sets', 'spotlight', 'tracks'];

	public function __construct()
	{
		parent::__construct(
			'soundcloud',
			'SoundCloud',
			['https://soundcloud.com/*', 'https://www.soundcloud.com/*', 'https://m.soundcloud.com/*'],
			'https://soundcloud.com/oembed'
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function request(string $url): string
	{
		return parent::request(self::permalink($url) ?? $url);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function frame(string $url, ?EmbedData $data): ?string
	{
		$permalink = self::permalink($url);

		return $permalink === null ? null : 'https://w.soundcloud.com/player/?' . http_build_query(['url' => $permalink, 'visual' => 'false', 'show_artwork' => 'true']);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function fixedHeight(string $url, ?EmbedData $data): int
	{
		return str_contains((string) self::permalink($url), '/sets/') ? self::SET : self::TRACK;
	}

	/**
	 * Returns a track's or playlist's plain link, or `null` for any other
	 * page.
	 */
	private static function permalink(string $url): ?string
	{
		$match = self::link(
			$url,
			['soundcloud.com', 'www.soundcloud.com', 'm.soundcloud.com'],
			'#^/([a-z0-9_-]+)/(sets/)?([a-z0-9_-]+)(?:/(s-[a-z0-9]+))?/?$#i'
		);

		if ($match === null) {
			return null;
		}

		// An optional group left unmatched at the end isn't in the list.
		[$account, $set, $slug] = $match;
		$secret                 = $match[3] ?? '';

		if ($set === '' && in_array(strtolower($slug), self::PAGES, true)) {
			return null;
		}

		return "https://soundcloud.com/{$account}/{$set}{$slug}" . ($secret === '' ? '' : "/{$secret}");
	}
}
