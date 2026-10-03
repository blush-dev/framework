<?php

/**
 * Markdown pages and llms.txt service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Llms;

use Blush\Core\ServiceProvider;
use Blush\Export\UrlSource;
use Blush\Routing\RouteSource;

/**
 * Binds Markdown pages and `llms.txt` (D-395).
 */
final class LlmsServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		MarkdownPages::class,
		MarkdownLinks::class,
		LlmsTxt::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array TRANSIENTS = [
		MarkdownController::class,
		LlmsTxtController::class,
		LlmsRoutes::class,
		LlmsExportUrls::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array TAGS = [
		RouteSource::TAG => [LlmsRoutes::class],
		UrlSource::TAG   => [LlmsExportUrls::class]
	];
}
