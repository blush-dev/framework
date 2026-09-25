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

/**
 * Binds the Markdown parser. An extension can replace the adapter by
 * binding its own `MarkdownParser`.
 */
final class MarkdownServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS_IF = [
		MarkdownParser::class => CommonMarkParser::class
	];
}
