<?php

/**
 * Admin asset controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Http\NotFound;
use Blush\Http\Response;
use Blush\Http\StreamException;

/**
 * Streams the admin app's built files from `{path}/assets/`. A URL with a
 * `?v=` version (as `AdminApp::url()` prints them) is cached for a year,
 * since a new build changes the version; without one, browsers check
 * back every time.
 */
final readonly class AssetController
{
	public function __construct(private AdminApp $app)
	{}

	/**
	 * @throws NotFound
	 * @throws StreamException
	 */
	public function __invoke(ServerRequestInterface $request, string $file): ResponseInterface
	{
		$path = $this->app->asset($file) ?? throw new NotFound(sprintf('There is no "%s" admin asset.', $file));
		$type = AdminApp::TYPES[strtolower(pathinfo($path, PATHINFO_EXTENSION))];

		$headers = [
			'Cache-Control'          => Response::fileCaching($request),
			'X-Content-Type-Options' => 'nosniff'
		];

		if ($type === 'image/svg+xml') {
			$headers['Content-Security-Policy'] = 'sandbox';
		}

		return Response::file($path, $type, $headers, $request->getHeaderLine('Range'));
	}
}
