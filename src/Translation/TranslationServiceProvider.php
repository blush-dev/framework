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
 * Binds the translator in the site locale (D-451), with the framework's
 * own `blush` domain and a domain for each enabled plugin and icon pack
 * that's on, by its `vendor/name` (its `lang` folder), with its namespace
 * mapped to it for directive and icon labels. The site's overrides in
 * `user/lang` win over them all. The view layer adds the theme chain's
 * domains.
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
			static function (ServiceResolver $resolver) use ($plugins, $packs): Translator {
				$paths      = $resolver->make(Paths::class);
				$domains    = ['blush' => [Framework::path('resources/lang')]];
				$namespaces = [];

				foreach ([...($plugins ? $resolver->make(Plugins::class)->all() : []), ...($packs ? $resolver->make(IconPacks::class)->enabled() : [])] as $extension) {
					$domains[$extension->name][]        = "{$extension->path}/lang";
					$namespaces[$extension->namespace] = $extension->name;
				}

				return new Translator(
					$resolver->make(DataLoader::class),
					$resolver->make(AppConfig::class)->locale,
					$domains,
					"{$paths->user}/lang",
					$namespaces
				);
			}
		);
	}
}
