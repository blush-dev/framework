<?php

/**
 * Spotify provider.
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
 * Spotify (D-634): track, album, playlist, artist, show, and episode
 * links (with or without a language path such as `/intl-de`), framed by
 * Spotify's embed player at the height Spotify answers with: 152 pixels
 * for a track or episode, 352 for the rest, with their track lists.
 * oEmbed is asked about the plain link, without share parameters.
 */
final class Spotify extends EmbedProvider
{
	/**
	 * The heights of Spotify's players, until it answers.
	 */
	private const int COMPACT = 152;

	private const int LIST = 352;

	public function __construct()
	{
		parent::__construct(
			'spotify',
			'Spotify',
			['https://open.spotify.com/*'],
			'https://open.spotify.com/oembed'
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function request(string $url): string
	{
		[$kind, $id] = self::item($url) ?? [null, null];

		return parent::request($kind === null ? $url : "https://open.spotify.com/{$kind}/{$id}");
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function frame(string $url, ?EmbedData $data): ?string
	{
		[$kind, $id] = self::item($url) ?? [null, null];

		return $kind === null ? null : "https://open.spotify.com/embed/{$kind}/{$id}";
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function fixedHeight(string $url, ?EmbedData $data): int
	{
		[$kind] = self::item($url) ?? [null];

		return $data->height ?? (in_array($kind, ['track', 'episode'], true) ? self::COMPACT : self::LIST);
	}

	/**
	 * Returns a link's kind (`track`) and ID, or `null`.
	 *
	 * @return ?array{string, string}
	 */
	private static function item(string $url): ?array
	{
		$match = self::link($url, ['open.spotify.com'], '#^/(?:intl-[a-z]{2}(?:-[a-z]{2})?/)?(track|album|playlist|artist|show|episode)/([a-z0-9]{22})/?$#i');

		return $match === null ? null : [strtolower($match[0]), $match[1]];
	}
}
