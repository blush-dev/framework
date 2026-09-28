<?php

/**
 * Vimeo provider.
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
 * Vimeo (D-113, D-184): video and player links. The frame is the player
 * with `dnt=1` (no tracking) and the link's start time (`#t=`, D-181). A
 * private link's hash (`vimeo.com/123/abc`, or `h` on the player) is
 * kept, since the video won't play without it; other player parameters
 * are dropped.
 */
final class Vimeo extends EmbedProvider
{
	public function __construct()
	{
		parent::__construct(
			'vimeo',
			'Vimeo',
			['https://vimeo.com/*', 'https://www.vimeo.com/*', 'https://player.vimeo.com/video/*'],
			'https://vimeo.com/api/oembed.json'
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function frame(string $url, ?EmbedData $data): ?string
	{
		[$id, $hash] = self::video($data?->frame() ?? '') ?? self::video($url) ?? [null, null];

		if ($id === null) {
			return null;
		}

		$uri = Uri::parse(trim($url));

		parse_str((string) $uri?->getFragment(), $fragment);
		parse_str((string) $uri?->getQuery(), $query);

		$start = self::seconds($fragment['t'] ?? $query['t'] ?? null);
		$query = http_build_query(array_filter(['h' => $hash, 'dnt' => 1], static fn (mixed $value): bool => $value !== null));

		return "https://player.vimeo.com/video/{$id}?{$query}" . ($start === null ? '' : "#t={$start}s");
	}

	/**
	 * Returns a Vimeo URL's video ID and private hash, or `null` when it
	 * has no video ID.
	 *
	 * @return ?array{string, ?string}
	 */
	private static function video(string $url): ?array
	{
		$uri = Uri::parse(trim($url));

		if ($uri === null || preg_match('#^/(?:video/)?(\d+)(?:/([0-9a-f]+))?/?$#', $uri->getPath(), $match) !== 1) {
			return null;
		}

		parse_str((string) $uri->getQuery(), $query);

		$hash = ($match[2] ?? '') !== '' ? $match[2] : (is_string($query['h'] ?? null) && ctype_alnum($query['h']) ? $query['h'] : null);

		return [$match[1], $hash];
	}
}
