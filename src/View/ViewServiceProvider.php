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
 * Binds the view layer: the view factory, and the themed renderers for
 * content pages and error pages. Both renderers are defaults an extension
 * can replace by binding its own.
 */
final class ViewServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		ViewFactory::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS_IF = [
		PageRenderer::class => ThemedPageRenderer::class,
		ErrorPages::class   => ThemedErrorPages::class
	];
}
