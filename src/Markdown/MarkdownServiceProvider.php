<?php

/**
 * Markdown service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Markdown;

use Blush\Core\ServiceProvider;
use Blush\Markdown\Html\MarkupFinder;

/**
 * Binds the Markdown parser, which is also the `MarkupFinder` (D-495).
 * An extension can replace the adapter by binding its own
 * `MarkdownParser` and `MarkupFinder`.
 */
final class MarkdownServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		CommonMarkParser::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS_IF = [
		MarkdownParser::class => CommonMarkParser::class,
		MarkupFinder::class   => CommonMarkParser::class
	];
}
