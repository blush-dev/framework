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
use Blush\Container\Container;
use Blush\Container\ServiceResolver;
use Blush\Core\AppConfig;
use Blush\Core\Framework;
use Blush\Core\Paths;
use Blush\Core\ServiceProvider;
use Blush\Data\DataLoader;
use Blush\Event\Listener\ListenerRegistry;
use Blush\Http\Events\RequestReceived;
use Blush\Icon\IconPacks;
use Blush\Plugin\Plugins;

/**
 * Binds the translator in the site locale (D-451), with the framework's
 * own `core` domain and a domain for each enabled plugin and icon pack
 * that's on, by its `vendor/name` (its `lang` folder), with its namespace
 * mapped to it for block and icon labels. The site's overrides in
 * `user/lang` win over them all. The view layer adds the theme chain's
 * domains.
 *
 * A class that asks for `Messages` gets its own extension's (D-784), by
 * its namespace (`ClassDomains`), translating in the current language
 * (`CurrentLocale`, D-816), which each request starts in the site's.
 */
final class TranslationServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	#[Override]
	public function register(): void
	{
		$this->container->singleton(ClassDomains::class);
		$this->container->singleton(
			CurrentLocale::class,
			static fn (ServiceResolver $resolver): CurrentLocale => new CurrentLocale($resolver->make(AppConfig::class)->locale)
		);
		$this->container->whenAnyNeedsType(
			Messages::class,
			static fn (Container $container, string $consumer): Messages => new Messages(
				$container->make(ClassDomains::class)->of($consumer),
				$container->make(Translator::class),
				$container->make(CurrentLocale::class)
			)
		);

		$plugins = $this->container->has(Plugins::class);
		$packs   = $this->container->has(IconPacks::class);

		$this->container->singleton(
			Translator::class,
			static function (ServiceResolver $resolver) use ($plugins, $packs): Translator {
				$paths      = $resolver->make(Paths::class);
				$domains    = [Translator::CORE => [Framework::path('resources/lang')]];
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

	/**
	 * Starts each request in the site's language (D-816).
	 */
	#[Override]
	public function boot(): void
	{
		if ($this->container->has(ListenerRegistry::class)) {
			$this->container->make(ListenerRegistry::class)->listen(RequestReceived::class, $this->container->make(CurrentLocale::class)->reset(...));
		}
	}
}
