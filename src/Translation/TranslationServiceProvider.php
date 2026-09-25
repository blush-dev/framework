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
use Blush\Core\ServiceProvider;
use Blush\Data\DataLoader;

/**
 * Binds the translator in the site locale, with the framework's own
 * `blush` domain. The view layer adds the `theme` domain for the theme
 * chain it renders with.
 */
final class TranslationServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	#[Override]
	public function register(): void
	{
		$this->container->singleton(
			Translator::class,
			static fn (ServiceResolver $resolver): Translator => new Translator(
				$resolver->make(DataLoader::class),
				$resolver->make(AppConfig::class)->locale,
				['blush' => [Framework::path('resources/lang')]]
			)
		);
	}
}
