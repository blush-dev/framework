<?php

/**
 * Theme asset controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Theme;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Http\NotFound;
use Blush\Http\Response;
use Blush\Http\StreamException;

/**
 * Streams a theme's asset (`/themes/{theme}/{path}`) through PHP, for
 * themes whose assets haven't been published to the public folder. Only
 * files with an allowed extension, outside the theme's private folders,
 * are served (`ThemeChain::isServable()`), and SVGs are sandboxed.
 */
final readonly class ThemeAssetController
{
	public function __construct(private Themes $themes)
	{}

	/**
	 * @throws NotFound
	 * @throws StreamException
	 * @throws ThemeException
	 */
	public function __invoke(ServerRequestInterface $request, string $theme, string $path): ResponseInterface
	{
		$manifest = $this->themes->find($theme);
		$file     = $manifest === null || ! ThemeChain::isServable($path) ? null : "{$manifest->path}/{$path}";

		if ($file === null || ! is_file($file)) {
			throw new NotFound(sprintf('There is no "%s" asset in the "%s" theme.', $path, $theme));
		}

		$headers = ['X-Content-Type-Options' => 'nosniff'];
		$mime    = ThemeChain::ASSET_TYPES[strtolower(pathinfo($path, PATHINFO_EXTENSION))];

		if ($mime === 'image/svg+xml') {
			$headers['Content-Security-Policy'] = 'sandbox';
		}

		return Response::file($file, $mime, $headers, $request->getHeaderLine('Range'));
	}
}
