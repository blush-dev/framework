<?php

/**
 * MCP service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Mcp;

use Override;
use Blush\Container\ServiceResolver;
use Blush\Core\Paths;
use Blush\Core\ServiceProvider;
use Blush\Mcp\Http\McpAuthentication;
use Blush\Mcp\Http\McpController;
use Blush\Mcp\Http\OAuthController;
use Blush\Mcp\OAuth\OAuthClients;
use Blush\Mcp\OAuth\OAuthGrants;
use Blush\Mcp\OAuth\OAuthServer;
use Blush\Routing\RouteSource;
use Blush\Storage\File\FileLayout;
use Blush\Storage\File\FileLayouts;
use Blush\Storage\Record\TableRegistry;

/**
 * Binds the MCP server (D-751 to D-758): the tools it offers (from
 * `Blush\Tool`, D-761), its protocol, OAuth, and its routes, and
 * registers OAuth's tables, kept with accounts (on files, under
 * `storage/oauth`).
 */
final class McpServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		McpTools::class,
		McpServer::class,
		McpStdio::class,
		McpInstructions::class,
		OAuthClients::class,
		OAuthGrants::class,
		OAuthServer::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array TRANSIENTS = [
		McpRoutes::class,
		McpAuthentication::class,
		McpController::class,
		OAuthController::class
	];

	/**
	 * @inheritDoc
	 */
	protected const array TAGS = [
		RouteSource::TAG => [McpRoutes::class]
	];

	#[Override]
	public function register(): void
	{
		$this->container->resolving(TableRegistry::class, static function (object $tables): void {
			if ($tables instanceof TableRegistry) {
				$tables->register(OAuthClients::table());
				$tables->register(OAuthGrants::grants());
				$tables->register(OAuthGrants::secrets());
			}
		});

		$this->container->resolving(FileLayouts::class, static function (object $layouts, ServiceResolver $resolver): void {
			if ($layouts instanceof FileLayouts) {
				$storage = $resolver->make(Paths::class)->storage;

				$layouts->register(OAuthClients::TABLE, FileLayout::folder("{$storage}/oauth/clients", 0660));
				$layouts->register(OAuthGrants::GRANTS, FileLayout::folder("{$storage}/oauth/grants", 0660));
				$layouts->register(OAuthGrants::SECRETS, FileLayout::folder("{$storage}/oauth/secrets", 0660));
			}
		});
	}
}
