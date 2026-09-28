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
use Blush\Extension\Extensions;

/**
 * Binds the translator in the site locale, with the framework's own
 * `blush` domain, the site's `app` domain (`resources/lang`), and a
 * domain for each extension vendor (every enabled extension's `lang`
 * folder, under the first part of its name: `acme/hello` is in `acme`).
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
		$extensions = $this->container->has(Extensions::class);

		$this->container->singleton(
			Translator::class,
			static fn (ServiceResolver $resolver): Translator => new Translator(
				$resolver->make(DataLoader::class),
				$resolver->make(AppConfig::class)->locale,
				[
					'blush' => [Framework::path('resources/lang')],
					'app'   => [$resolver->make(Paths::class)->resources . '/lang'],
					...($extensions ? self::vendorDomains($resolver->make(Extensions::class)) : [])
				]
			)
		);
	}

	/**
	 * Returns each extension vendor's catalog folders.
	 *
	 * @return array<string, list<string>>
	 */
	private static function vendorDomains(Extensions $extensions): array
	{
		$domains = [];

		foreach ($extensions->all() as $extension) {
			$vendor = strstr($extension->name, '/', true);

			if ($vendor !== false && ! in_array($vendor, ['blush', 'app', 'theme'], true)) {
				$domains[$vendor][] = "{$extension->path}/lang";
			}
		}

		return $domains;
	}
}
