<?php

/**
 * MCP routes.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Mcp;

use Override;
use Blush\Api\ApiConfig;
use Blush\Mcp\Http\McpAuthentication;
use Blush\Mcp\Http\McpController;
use Blush\Mcp\Http\OAuthController;
use Blush\Routing\Route;
use Blush\Routing\RoutePriority;
use Blush\Routing\RouteSource;

/**
 * The MCP server's addresses (D-751, D-752), only while it's on (D-754):
 * `{path}/mcp` at the API's `path`, and OAuth's well-known and `/oauth`
 * addresses. Off, they're the site's own 404.
 */
final readonly class McpRoutes implements RouteSource
{
	public function __construct(
		private McpConfig $config,
		private ApiConfig $api
	) {}

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

		$mcp = $this->api->path . '/mcp';

		return [
			Route::post($mcp, [McpController::class, 'post'])->named('mcp')->middleware(McpAuthentication::class),
			Route::match(['GET', 'DELETE'], $mcp, [McpController::class, 'refuse'])->named('mcp.refuse'),
			Route::match(['OPTIONS'], $mcp, [McpController::class, 'preflight'])->named('mcp.preflight'),
			Route::get('/.well-known/oauth-protected-resource', [OAuthController::class, 'resource'])->named('oauth.resource'),
			Route::get('/.well-known/oauth-protected-resource' . $mcp, [OAuthController::class, 'resource'])->named('oauth.resource.mcp'),
			Route::get('/.well-known/oauth-authorization-server', [OAuthController::class, 'server'])->named('oauth.server'),
			Route::post('/oauth/register', [OAuthController::class, 'register'])->named('oauth.register'),
			Route::post('/oauth/token', [OAuthController::class, 'token'])->named('oauth.token'),
			Route::post('/oauth/revoke', [OAuthController::class, 'revoke'])->named('oauth.revoke'),
			Route::match(['OPTIONS'], '/.well-known/{path:oauth-.+}', [OAuthController::class, 'preflight'])->named('oauth.preflight.known'),
			Route::match(['OPTIONS'], '/oauth/{path:.+}', [OAuthController::class, 'preflight'])->named('oauth.preflight')
		];
	}
}
