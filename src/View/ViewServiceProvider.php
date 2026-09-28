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

use Blush\Content\Http\PageRenderer;
use Blush\Core\ServiceProvider;
use Blush\Http\ErrorPages;

/**
 * Binds the view layer: the view factory, context providers, and the
 * themed renderers for content pages and error pages. The renderers are
 * defaults an extension can replace by binding its own. Components have
 * their own provider (`Component\ComponentServiceProvider`).
 *
 * A theme or site provider adds a context provider in `boot()`:
 *
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
		ContextProviders::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS_IF = [
		PageRenderer::class => ThemedPageRenderer::class,
		ErrorPages::class   => ThemedErrorPages::class
	];
}
