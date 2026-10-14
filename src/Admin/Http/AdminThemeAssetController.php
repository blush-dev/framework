<?php

/**
 * Admin theme asset controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Admin\Theme\AdminTheme;
use Blush\Admin\Theme\AdminThemes;
use Blush\Http\NotFound;
use Blush\Http\Response;
use Blush\Http\StreamException;

/**
 * Serves an admin theme's files from
 * `{path}/assets/admin-themes/{vendor}/{name}/` (D-827): its stylesheet,
 * `theme.css`, which Blush writes from its `tokens.json`, and the font
 * files its `fontFace` names, which the stylesheet points to beside it.
 * Nothing else in the theme's folder is served, and only a theme that's
 * offered is. Like the app's own files, a URL with a `?v=` version is
 * cached for a year. No session is needed, since the sign-in screen shows
 * the site's default theme.
 */
final readonly class AdminThemeAssetController
{
	public function __construct(private AdminThemes $themes)
	{}

	/**
	 * @throws NotFound
	 * @throws StreamException
	 */
	public function __invoke(ServerRequestInterface $request, string $vendor, string $name, string $file): ResponseInterface
	{
		$theme = $this->themes->enabled()["{$vendor}/{$name}"] ?? throw new NotFound(sprintf('There is no "%s/%s" admin theme turned on.', $vendor, $name));

		$headers = [
			'Cache-Control'          => Response::fileCaching($request),
			'X-Content-Type-Options' => 'nosniff'
		];

		if ($file === AdminTheme::STYLESHEET) {
			return Response::text($theme->stylesheet(), headers: ['Content-Type' => 'text/css; charset=UTF-8', ...$headers]);
		}

		$path = $theme->font($file) ?? throw new NotFound(sprintf('The "%s" admin theme has no "%s".', $theme->name, $file));

		return Response::file($path, 'font/woff2', $headers, $request->getHeaderLine('Range'));
	}
}
