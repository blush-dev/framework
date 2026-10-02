<?php

/**
 * Icon service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Icon;

use Override;
use Blush\Container\ServiceResolver;
use Blush\Core\ServiceProvider;

/**
 * Binds icon lookup (D-187): the registry plugins add icon folders to,
 * holding every installed icon pack's folder from the start (D-378), and
 * `Icons`.
 */
final class IconServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		Icons::class
	];

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function register(): void
	{
		$packs = $this->container->has(IconPacks::class);

		$this->container->singleton(IconRegistry::class, static function (ServiceResolver $resolver) use ($packs): IconRegistry {
			$registry = new IconRegistry();

			foreach ($packs ? $resolver->make(IconPacks::class)->enabled() : [] as $pack) {
				$registry->add($pack->namespace, $pack->iconsPath());
			}

			return $registry;
		});
	}
}
