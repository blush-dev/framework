<?php

/**
 * Translation service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Translation;

use Override;
use Blush\Container\ServiceResolver;
use Blush\Core\AppConfig;
use Blush\Core\Framework;
use Blush\Core\Paths;
use Blush\Core\ServiceProvider;
use Blush\Data\DataLoader;
use Blush\Icon\IconPacks;
use Blush\Plugin\Plugins;

/**
 * Binds the translator in the site locale, with the framework's own
 * `blush` domain, the site's `app` domain (`resources/lang`), and a
 * domain for each enabled plugin and installed icon pack, its namespace
 * (its `lang` folder: `acme/hello`, with the namespace `hello`, is in
 * `hello`; D-378).
 * These match component namespaces (D-171, D-172). The view layer adds
 * the `theme` domain for the theme chain it renders with.
 */
final class TranslationServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	#[Override]
	public function register(): void
	{
		$plugins = $this->container->has(Plugins::class);
		$packs   = $this->container->has(IconPacks::class);

		$this->container->singleton(
			Translator::class,
			static fn (ServiceResolver $resolver): Translator => new Translator(
				$resolver->make(DataLoader::class),
				$resolver->make(AppConfig::class)->locale,
				[
					'blush' => [Framework::path('resources/lang')],
					'app'   => [$resolver->make(Paths::class)->resources . '/lang'],
					...($plugins ? self::pluginDomains($resolver->make(Plugins::class)) : []),
					...($packs ? self::packDomains($resolver->make(IconPacks::class)) : [])
				]
			)
		);
	}

	/**
	 * Returns each plugin namespace's catalog folders.
	 *
	 * @return array<string, list<string>>
	 */
	private static function pluginDomains(Plugins $plugins): array
	{
		$domains = [];

		foreach ($plugins->all() as $plugin) {
			$domains[$plugin->namespace][] = "{$plugin->path}/lang";
		}

		return $domains;
	}

	/**
	 * Returns the catalog folder of each icon pack that's on, by
	 * namespace.
	 *
	 * @return array<string, list<string>>
	 */
	private static function packDomains(IconPacks $packs): array
	{
		$domains = [];

		foreach ($packs->enabled() as $pack) {
			$domains[$pack->namespace][] = $pack->langPath();
		}

		return $domains;
	}
}
