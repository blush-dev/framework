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
use Blush\Container\ContainerException;
use Blush\Core\AppConfig;
use Blush\Core\Application;
use Blush\Core\ServiceProvider;
use Blush\Extension\ExtensionState;
use Blush\Extension\LocalAutoloader;

/**
 * Picks the theme chain a request renders with: the configured active
 * theme, or, in development only, an installed theme named by
 * `?theme={name}` (D-035). An unknown `?theme=` is ignored. A previewed
 * theme runs as it would if it were active: its chain's local themes are
 * autoloaded and its providers registered, when its requirements are met.
 * The active theme's providers have run by then too, so what they
 * register stays.
 *
 * Chains are built once per theme and kept. The chain a request picked is
 * remembered as `current()`, which Markdown directives render with.
 */
final class ThemeResolver
{
	/**
	 * Chains built so far, by theme name.
	 *
	 * @var array<string, ThemeChain>
	 */
	private array $chains = [];

	/**
	 * The chain the last request rendered with.
	 */
	private ?ThemeChain $current = null;

	/**
	 * The previewed themes started so far, by name.
	 *
	 * @var array<string, true>
	 */
	private array $started = [];

	public function __construct(
		private readonly Themes $themes,
		private readonly ThemeConfig $config,
		private readonly AppConfig $app,
		private readonly ExtensionState $extensions,
		private readonly Application $application
	) {}

	/**
	 * Returns the active theme's chain, or the default theme's when the
	 * active chain's requirements aren't met (D-431).
	 *
	 * @throws ThemeException When the active theme or an ancestor is missing.
	 */
	public function active(): ThemeChain
	{
		return $this->chain($this->themes->running($this->config->active));
	}

	/**
	 * Returns the chain a request renders with.
	 *
	 * @throws ThemeException
	 */
	public function forRequest(ServerRequestInterface $request): ThemeChain
	{
		$name = $request->getQueryParams()['theme'] ?? null;

		if (! $this->app->environment->isDevelopment() || ! is_string($name) || ! $this->themes->has($name)) {
			return $this->current = $this->active();
		}

		$this->start($name);

		return $this->current = $this->chain($name);
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
	 * Starts a theme that isn't running, once, as activating it would: for
	 * a preview (`?theme=`), or `theme:check` on an inactive theme. Its
	 * chain's local themes are autoloaded and its providers registered
	 * (ancestors first; any the active chain shares are already
	 * registered), unless it's the running theme or its requirements
	 * aren't met. A provider that isn't a service provider is skipped, as
	 * at boot.
	 *
	 * @throws ContainerException
	 * @throws ThemeException When the theme or an ancestor is missing.
	 */
	public function start(string $name): void
	{
		if (isset($this->started[$name]) || $name === $this->themes->running($this->config->active)) {
			return;
		}

		$this->started[$name] = true;

		if ($this->extensions->with(theme: $name)->themes->unmet() !== []) {
			return;
		}

		$chain      = $this->chain($name);
		$autoloader = new LocalAutoloader();
		$autoloader->addThemes($chain);
		$autoloader->register();

		$this->application->register(...array_values(array_filter(
			$chain->providers(),
			static fn (string $provider): bool => is_subclass_of($provider, ServiceProvider::class)
		)));
	}

	/**
	 * Returns a theme's chain.
	 *
	 * @throws ThemeException
	 */
	public function chain(string $name): ThemeChain
	{
		return $this->chains[$name] ??= $this->themes->chain($name);
	}
}
