<?php

/**
 * Embed providers.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Embed;

/**
 * Every embed provider the site has: those in `config/embed.php` first,
 * then the registered ones (a configured provider replaces a registered
 * one of the same name). A URL is embedded by the first that matches it.
 */
final class EmbedProviders
{
	/**
	 * The providers, built on first use.
	 *
	 * @var ?list<EmbedProvider>
	 */
	private ?array $providers = null;

	public function __construct(
		private readonly ProviderRegistry $registry,
		private readonly ProviderFactory $factory,
		private readonly EmbedConfig $config
	) {}

	/**
	 * Returns every provider, in the order URLs are matched.
	 *
	 * @return list<EmbedProvider>
	 * @throws EmbedException When a registered provider can't be built.
	 */
	public function all(): array
	{
		if ($this->providers !== null) {
			return $this->providers;
		}

		$providers = $this->config->providers;
		$names     = array_map(static fn (EmbedProvider $provider): string => $provider->name, $providers);

		foreach (array_keys($this->registry->all()) as $name) {
			$provider = in_array($name, $names, true) ? null : $this->factory->make($name);

			if ($provider !== null) {
				$providers[] = $provider;
			}
		}

		return $this->providers = $providers;
	}

	/**
	 * Returns the provider that embeds a URL, or `null`.
	 *
	 * @throws EmbedException
	 */
	public function forUrl(string $url): ?EmbedProvider
	{
		return array_find($this->all(), static fn (EmbedProvider $provider): bool => $provider->matches($url));
	}
}
