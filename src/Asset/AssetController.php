<?php

/**
 * Asset controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Asset;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Http\NotFound;
use Blush\Http\Response;
use Blush\Http\StreamException;
use Blush\Theme\ThemeChain;

/**
 * Streams core's site files (`/blush/{path}`) and plugins' files
 * (`/extensions/{vendor}/{name}/{path}`) through PHP (D-569). Only files
 * a theme could serve are served (`AssetUrls`), and SVGs are sandboxed.
 * A URL with a `?v=` version, as `AssetUrls` prints them, is cached for
 * a year, since a changed file changes its version; without one,
 * browsers check back every time.
 */
final readonly class AssetController
{
	public function __construct(private AssetUrls $urls)
	{}

	/**
	 * Streams one of core's site files.
	 *
	 * @throws NotFound
	 * @throws StreamException
	 */
	public function core(ServerRequestInterface $request, string $path): ResponseInterface
	{
		$file = $this->urls->corePath($path) ?? throw new NotFound(sprintf('There is no "%s" core asset.', $path));

		return self::send($request, $file);
	}

	/**
	 * Streams a file in a plugin that runs.
	 *
	 * @throws NotFound
	 * @throws StreamException
	 */
	public function plugin(ServerRequestInterface $request, string $plugin, string $path): ResponseInterface
	{
		$file = $this->urls->pluginPath($plugin, $path) ?? throw new NotFound(sprintf('There is no "%s" asset in the "%s" plugin.', $path, $plugin));

		return self::send($request, $file);
	}

	/**
	 * Streams a servable file.
	 *
	 * @throws StreamException
	 */
	private static function send(ServerRequestInterface $request, string $file): ResponseInterface
	{
		$type    = ThemeChain::ASSET_TYPES[strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
		$headers = [
			'Cache-Control'          => Response::fileCaching($request),
			'X-Content-Type-Options' => 'nosniff'
		];

		if ($type === 'image/svg+xml') {
			$headers['Content-Security-Policy'] = 'sandbox';
		}

		return Response::file($file, $type, $headers, $request->getHeaderLine('Range'));
	}
}
