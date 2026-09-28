<?php

/**
 * YouTube provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Embed\Providers;

use Override;
use Uri\Rfc3986\Uri;
use Blush\Embed\EmbedData;
use Blush\Embed\EmbedProvider;

/**
 * YouTube (D-113, D-184): watch, short, live, embed, and `youtu.be` links.
 * The frame is always `youtube-nocookie.com` (no tracking cookies until
 * a visitor plays it) with the link's start time (`t` or `start`, D-181);
 * other query parameters, such as the `si` share ID, are dropped. oEmbed
 * is asked about the plain watch URL (or the short's), for its size and
 * title.
 */
final class YouTube extends EmbedProvider
{
	public function __construct()
	{
		parent::__construct(
			'youtube',
			'YouTube',
			[
				'https://youtube.com/watch*',
				'https://www.youtube.com/watch*',
				'https://m.youtube.com/watch*',
				'https://youtube.com/shorts/*',
				'https://www.youtube.com/shorts/*',
				'https://youtube.com/live/*',
				'https://www.youtube.com/live/*',
				'https://youtube.com/embed/*',
				'https://www.youtube.com/embed/*',
				'https://www.youtube-nocookie.com/embed/*',
				'https://youtu.be/*'
			],
			'https://www.youtube.com/oembed'
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function request(string $url): string
	{
		[$id, , $short] = self::video($url);

		return parent::request($id === null ? $url : ($short ? "https://www.youtube.com/shorts/{$id}" : "https://www.youtube.com/watch?v={$id}"));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function frame(string $url, ?EmbedData $data): ?string
	{
		[$id, $start] = self::video($url);

		return $id === null ? null : "https://www.youtube-nocookie.com/embed/{$id}" . ($start === null ? '' : "?start={$start}");
	}

	/**
	 * Returns a link's video ID (or `null`), start time, and whether it's
	 * a short.
	 *
	 * @return array{?string, ?int, bool}
	 */
	private static function video(string $url): array
	{
		$uri = Uri::parse(trim($url));

		if ($uri === null) {
			return [null, null, false];
		}

		$host = strtolower((string) $uri->getHost());
		$path = $uri->getPath();

		parse_str((string) $uri->getQuery(), $query);

		$id = match (true) {
			$host === 'youtu.be'                                                     => trim($path, '/'),
			preg_match('#^/(?:embed|shorts|live)/([^/]+)#', $path, $match) === 1 => $match[1],
			default                                                                  => is_string($query['v'] ?? null) ? $query['v'] : null
		};

		if ($id === null || preg_match('/^[A-Za-z0-9_-]{6,20}$/', $id) !== 1) {
			return [null, null, false];
		}

		return [$id, self::seconds($query['t'] ?? $query['start'] ?? null), str_starts_with($path, '/shorts/')];
	}
}
