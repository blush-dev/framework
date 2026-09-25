<?php

/**
 * Front matter redirects.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Routing;

use Override;
use Uri\Rfc3986\Uri;
use Blush\Content\ContentRepository;
use Blush\Routing\Redirect;
use Blush\Routing\RedirectSource;

/**
 * Redirects from each entry's `redirect_from` front matter to the entry's
 * URL. A value may be a path (`/old/post`, with or without its slashes)
 * or a full URL, whose path is used. Paths with `{` or `}` are skipped,
 * since redirect paths are route patterns. Entries without a URL (drafts,
 * hidden entries) redirect nowhere.
 */
final readonly class ContentRedirects implements RedirectSource
{
	public function __construct(
		private ContentRepository $content,
		private ContentUrls $urls
	) {}

	/**
	 * @inheritDoc
	 * @return list<Redirect>
	 */
	#[Override]
	public function redirects(): iterable
	{
		$redirects = [];

		foreach ($this->content->redirects() as $from => $entry) {
			$to   = $entry->isPublished() ? $this->urls->entry($entry) : null;
			$path = self::path($from);

			if ($to !== null && $path !== null && $path !== $to) {
				$redirects[] = new Redirect($path, $to);
			}
		}

		return $redirects;
	}

	/**
	 * Returns the path a `redirect_from` value names, or `null`.
	 */
	private static function path(string $from): ?string
	{
		if (str_contains($from, '://')) {
			$from = Uri::parse($from)?->getRawPath() ?? '';
		}

		$path = '/' . trim($from, '/');

		return $path === '/' || strpbrk($path, '{}') !== false ? null : $path;
	}
}
