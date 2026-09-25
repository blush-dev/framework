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
use Blush\Markdown\DirectiveRenderer;
use Blush\View\Component\ComponentFactory;
use Blush\View\Component\ComponentRegistrar;
use Blush\View\Component\ComponentRegistry;

/**
 * Binds the view layer: the view factory, components, context providers,
 * and the themed renderers for content pages, error pages, and Markdown
 * directives. The renderers are defaults an extension can replace by
 * binding its own.
 *
 * A theme or site provider adds a class-backed component or a context
 * provider in `boot()`:
 *
 *     $this->container->make(ComponentRegistry::class)->register('card', Card::class);
 *     $this->container->make(ContextProviders::class)->add('parts/header', PrimaryMenu::class);
 */
final class ViewServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		ViewFactory::class,
		ViewServices::class,
		DocumentRenderer::class,
		ContextProviders::class,
		ComponentFactory::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS_IF = [
		PageRenderer::class      => ThemedPageRenderer::class,
		ErrorPages::class        => ThemedErrorPages::class,
		DirectiveRenderer::class => ComponentDirectives::class
	];

	/**
	 * Binds the component registry, seeded with the built-ins.
	 */
	#[Override]
	public function register(): void
	{
		$this->container->singleton(
			ComponentRegistry::class,
			static function (): ComponentRegistry {
				$registry = new ComponentRegistry();
				new ComponentRegistrar($registry)->register();

				return $registry;
			}
		);
	}
}
