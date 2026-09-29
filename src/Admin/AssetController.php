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
 * Streams the admin app's built files from `{path}/assets/`. Vite names
 * them by a hash of their contents, so they're cached for a year.
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
			'Cache-Control'          => 'public, max-age=31536000, immutable',
			'X-Content-Type-Options' => 'nosniff'
		];

		if ($type === 'image/svg+xml') {
			$headers['Content-Security-Policy'] = 'sandbox';
		}

		return Response::file($path, $type, $headers, $request->getHeaderLine('Range'));
	}
}
