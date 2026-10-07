<?php

/**
 * Context providers.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View;

use Blush\Container\Container;

/**
 * The context providers attached to view names. A pattern is a view name
 * or an `fnmatch()` pattern where `*` stays within a folder (`partials/*`,
 * `single-*`); `**` isn't special. Providers given as class names are
 * built through the container on first use and kept.
 */
final class ContextProviders
{
	/**
	 * Patterns and their providers, in the order added.
	 *
	 * @var list<array{string, class-string<ContextProvider>|ContextProvider}>
	 */
	private array $providers = [];

	public function __construct(private readonly Container $container)
	{}

	/**
	 * Attaches a provider to a view name or pattern.
	 *
	 * @param class-string<ContextProvider>|ContextProvider $provider
	 */
	public function add(string $pattern, string|ContextProvider $provider): void
	{
		$this->providers[] = [$pattern, $provider];
	}

	/**
	 * Returns whether any provider matches a view.
	 */
	public function has(string $view): bool
	{
		return array_any($this->providers, static fn (array $provider): bool => fnmatch($provider[0], $view, FNM_PATHNAME));
	}

	/**
	 * Returns a view's data with every matching provider's added under
	 * it, in the order providers were added.
	 *
	 * @param  array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	public function apply(string $view, array $data): array
	{
		foreach ($this->providers as $index => [$pattern, $provider]) {
			if (! fnmatch($pattern, $view, FNM_PATHNAME)) {
				continue;
			}

			if (is_string($provider)) {
				$provider = $this->container->make($provider);

				$this->providers[$index][1] = $provider;
			}

			$data = [...$provider->provide($view, $data), ...$data];
		}

		return $data;
	}
}
