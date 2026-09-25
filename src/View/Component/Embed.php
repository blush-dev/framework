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

namespace Blush\View\Component;

use Uri\Rfc3986\Uri;

/**
 * Embeds a video by its page URL: `::embed[Caption]{url="https://youtu.be/…"}`.
 * YouTube (through `youtube-nocookie.com`) and Vimeo (with `dnt=1`) become
 * an `<iframe>`; any other URL renders as a link, so content never loads
 * a frame from an unknown origin.
 *
 * Its template gets `$url`, `$title`, `$provider` (`youtube`, `vimeo`, or
 * `''`), and `$src` (the frame URL, or `null`).
 */
final class Embed extends Component
{
	/**
	 * The service the URL belongs to, or `''`.
	 */
	public readonly string $provider;

	/**
	 * The frame URL, or `null` when the URL isn't embeddable.
	 */
	public readonly ?string $src;

	public function __construct(
		public readonly string $url = '',
		public readonly string $title = ''
	) {
		[$this->provider, $this->src] = self::frame($url);
	}

	/**
	 * @inheritDoc
	 */
	public function shouldRender(): bool
	{
		return $this->url !== '';
	}

	/**
	 * Returns the provider and frame URL for a page URL.
	 *
	 * @return array{string, ?string}
	 */
	private static function frame(string $url): array
	{
		$uri = Uri::parse($url);

		if ($uri === null || ! in_array($uri->getScheme(), ['http', 'https'], true)) {
			return ['', null];
		}

		$host = strtolower((string) $uri->getHost());
		$host = str_starts_with($host, 'www.') ? substr($host, 4) : $host;
		$path = $uri->getPath();
		$id   = null;

		if ($host === 'youtu.be') {
			$id = trim($path, '/');
		} elseif (in_array($host, ['youtube.com', 'm.youtube.com', 'youtube-nocookie.com'], true)) {
			parse_str((string) $uri->getQuery(), $query);

			$id = preg_match('#^/(?:embed|shorts|live)/([^/]+)#', $path, $match) === 1
				? $match[1]
				: (is_string($query['v'] ?? null) ? $query['v'] : null);
		}

		if ($id !== null && preg_match('/^[A-Za-z0-9_-]{6,20}$/', $id) === 1) {
			return ['youtube', "https://www.youtube-nocookie.com/embed/{$id}"];
		}

		if (in_array($host, ['vimeo.com', 'player.vimeo.com'], true) && preg_match('#/(\d+)(?:/|$)#', $path, $match) === 1) {
			return ['vimeo', "https://player.vimeo.com/video/{$match[1]}?dnt=1"];
		}

		return ['', null];
	}
}
