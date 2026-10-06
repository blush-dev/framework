<?php

/**
 * Directive service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Directive;

use Override;
use Blush\Core\ServiceProvider;
use Blush\Theme\Themes;

/**
 * Binds directives (D-026, D-532): the registry, seeded with the
 * built-ins, and the Markdown directive renderer, a default an extension
 * can replace by binding its own.
 *
 * A plugin or site provider registers a directive in `boot()` (a theme
 * can't):
 *
 *     $this->container->make(DirectiveRegistry::class)->register('app/pricing', Pricing::class);
 */
final class DirectiveServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS_IF = [
		DirectiveRenderer::class => MarkdownDirectives::class
	];

	/**
	 * Binds the directive registry, seeded with the built-ins.
	 */
	#[Override]
	public function register(): void
	{
		$container = $this->container;

		$this->container->singleton(
			DirectiveRegistry::class,
			static function () use ($container): DirectiveRegistry {
				// Themes can't register directives (D-532); the installed
				// themes are looked up only when a provider registers one.
				$registry = new DirectiveRegistry(static fn (string $namespace): bool => $container->make(Themes::class)->byNamespace($namespace) !== null);
				new DirectiveRegistrar($registry)->register();

				return $registry;
			}
		);
	}
}
