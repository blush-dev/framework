<?php

/**
 * Rendered bodies.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Cache;

use Closure;
use Override;
use Blush\Content\Entry\BodyCache;
use Blush\Core\AppConfig;
use Blush\Core\Framework;
use Blush\Markdown\MarkdownConfig;
use Blush\Media\MediaConfig;
use Blush\Theme\ThemeException;
use Blush\Theme\ThemeResolver;

/**
 * The framework's `BodyCache`: rendered bodies and summaries in the
 * `bodies` store, keyed by the content version, the body's own key (its
 * content hash), the theme chain the request renders with (directives
 * render as the theme's components, D-112), and a fingerprint of the
 * rendering settings (the Markdown and media config, the site URL, and
 * the framework version).
 *
 * The content version is in the key because a directive's component may
 * read other content or site data; a publish re-renders bodies lazily,
 * as pages ask for them (D-130).
 */
final class RenderedBodies implements BodyCache
{
	private ?string $fingerprint = null;

	public function __construct(
		private readonly ContentCache $cache,
		private readonly ThemeResolver $themes,
		private readonly MarkdownConfig $markdown,
		private readonly MediaConfig $media,
		private readonly AppConfig $app
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function remember(string $key, Closure $render): string
	{
		return $this->cache->remember(CacheNamespace::Bodies, "{$this->theme()} {$this->fingerprint()} {$key}", $render);
	}

	/**
	 * Returns the name of the theme the current request renders with, or
	 * `''` when its chain is broken (rendering reports that).
	 */
	private function theme(): string
	{
		try {
			return $this->themes->current()->active()->name;
		} catch (ThemeException) {
			return '';
		}
	}

	/**
	 * Returns a hash of the settings a rendering depends on.
	 */
	private function fingerprint(): string
	{
		return $this->fingerprint ??= hash('xxh128', serialize([
			Framework::VERSION,
			$this->app->url,
			$this->markdown->toArray(),
			$this->media->toArray()
		]));
	}
}
