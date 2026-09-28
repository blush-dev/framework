<?php

/**
 * Embed service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Embed;

use Override;
use Blush\Core\ServiceProvider;

/**
 * Binds oEmbed lookups (D-184): the provider registry (seeded with the
 * built-ins), the providers, the `Embeds` service, and the default
 * `Fetcher`, which a site or extension can replace by binding its own.
 */
final class EmbedServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		EmbedProviders::class,
		Embeds::class,
		ProviderFactory::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS_IF = [
		Fetcher::class => StreamFetcher::class
	];

	/**
	 * Binds the provider registry, seeded with the built-ins.
	 */
	#[Override]
	public function register(): void
	{
		$this->container->singleton(
			ProviderRegistry::class,
			static function (): ProviderRegistry {
				$registry = new ProviderRegistry();
				new ProviderRegistrar($registry)->register();

				return $registry;
			}
		);
	}
}
