<?php

/**
 * View service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View;

use Override;
use Blush\Content\Http\PageRenderer;
use Blush\Core\ServiceProvider;
use Blush\Http\ErrorPages;
use Blush\View\Engine\ViewEngineRegistrar;
use Blush\View\Engine\ViewEngineRegistry;
use Blush\View\Engine\ViewEngines;

/**
 * Binds the view layer: the view engines (D-502), the view factory,
 * context providers, and the themed renderers for content pages and
 * error pages. The renderers are
 * defaults an extension can replace by binding its own. Directives and
 * components have their own providers (`DirectiveServiceProvider`,
 * `ComponentServiceProvider`).
 *
 * A theme or site provider adds a context provider in `boot()`:
 *
 *     $this->container->make(ContextProviders::class)->add('parts/header', PrimaryMenu::class);
 *
 * A plugin adds a view engine the same way:
 *
 *     $this->container->make(ViewEngineRegistry::class)->register('twig', TwigEngine::class);
 */
final class ViewServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		ViewEngines::class,
		ViewFactory::class,
		ViewServices::class,
		DocumentRenderer::class,
		ContextProviders::class,
		RenderableFactory::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS_IF = [
		PageRenderer::class => ThemedPageRenderer::class,
		ErrorPages::class   => ThemedErrorPages::class
	];

	/**
	 * Binds the engine registry, seeded with the built-ins.
	 */
	#[Override]
	public function register(): void
	{
		$this->container->singleton(
			ViewEngineRegistry::class,
			static function (): ViewEngineRegistry {
				$registry = new ViewEngineRegistry();
				new ViewEngineRegistrar($registry)->register();

				return $registry;
			}
		);
	}
}
