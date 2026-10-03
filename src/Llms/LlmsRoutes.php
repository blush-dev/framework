<?php

/**
 * Markdown page and llms.txt routes.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Llms;

use Override;
use Blush\Routing\Route;
use Blush\Routing\RoutePriority;
use Blush\Routing\RouteSource;

/**
 * With `LlmsConfig::$enabled` on, the system routes `llms`
 * (`/llms.txt`), `llms.markdown` (any path ending in `.md`), and, with
 * `$full` on too, `llms.full` (`/llms-full.txt`, D-402).
 *
 * The Markdown route must come before content routes, whose `{name}`
 * would take `hello.md`, and after the other system routes (the admin,
 * media), whose own paths may end in `.md`: its provider registers last
 * among the framework's system routes.
 */
final readonly class LlmsRoutes implements RouteSource
{
	/**
	 * Where `llms-full.txt` is served.
	 */
	public const string FULL = '/llms-full.txt';

	public function __construct(private LlmsConfig $config)
	{}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function priority(): RoutePriority
	{
		return RoutePriority::System;
	}

	/**
	 * @inheritDoc
	 * @return list<Route>
	 */
	#[Override]
	public function routes(): iterable
	{
		if (! $this->config->enabled) {
			return [];
		}

		return [
			Route::get('/llms.txt', LlmsTxtController::class)->named('llms'),
			...($this->config->full ? [Route::get(self::FULL, LlmsTxtController::class)->named('llms.full')] : []),
			Route::get('/{path:.+}.md', MarkdownController::class)->named('llms.markdown')
		];
	}
}
