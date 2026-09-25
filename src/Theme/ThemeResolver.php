<?php

/**
 * Theme resolver.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Theme;

use Psr\Http\Message\ServerRequestInterface;
use Blush\Core\AppConfig;

/**
 * Picks the theme chain a request renders with: the configured active
 * theme, or, in development only, an installed theme named by
 * `?theme={slug}` (D-035). An unknown `?theme=` is ignored.
 *
 * Chains are built once per slug and kept. The chain a request picked is
 * remembered as `current()`, which Markdown directives render with.
 */
final class ThemeResolver
{
	/**
	 * Chains built so far, by slug.
	 *
	 * @var array<string, ThemeChain>
	 */
	private array $chains = [];

	/**
	 * The chain the last request rendered with.
	 */
	private ?ThemeChain $current = null;

	public function __construct(
		private readonly Themes $themes,
		private readonly ThemeConfig $config,
		private readonly AppConfig $app
	) {}

	/**
	 * Returns the active theme's chain.
	 *
	 * @throws ThemeException When the active theme or an ancestor is missing.
	 */
	public function active(): ThemeChain
	{
		return $this->chain($this->config->active);
	}

	/**
	 * Returns the chain a request renders with.
	 *
	 * @throws ThemeException
	 */
	public function forRequest(ServerRequestInterface $request): ThemeChain
	{
		$slug = $request->getQueryParams()['theme'] ?? null;

		return $this->current = $this->app->environment->isDevelopment() && is_string($slug) && $this->themes->has($slug)
			? $this->chain($slug)
			: $this->active();
	}

	/**
	 * Returns the chain the current request renders with, for rendering
	 * that doesn't see the request (Markdown directives inside entry
	 * bodies), or the active chain before any request.
	 *
	 * @throws ThemeException
	 */
	public function current(): ThemeChain
	{
		return $this->current ?? $this->active();
	}

	/**
	 * Returns a theme's chain.
	 *
	 * @throws ThemeException
	 */
	public function chain(string $slug): ThemeChain
	{
		return $this->chains[$slug] ??= $this->themes->chain($slug);
	}
}
