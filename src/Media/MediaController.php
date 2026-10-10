<?php

/**
 * Media streaming controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Http\NotFound;
use Blush\Http\Response;
use Blush\Http\StreamException;

/**
 * Streams media through PHP when the web server can't serve it directly:
 * `user/media` before `media:publish` has run (or on hosts that can't
 * link it). Byte ranges
 * work, so audio and video can seek. Only allowed types resolve; SVGs are
 * sandboxed so scripts in them can't run on the site's origin. Media
 * URLs have no version, so browsers check back every time, and a file
 * that hasn't changed costs them a 304 (D-699).
 */
final readonly class MediaController
{
	public function __construct(
		private MediaResolver $media,
		private MediaConfig $config
	) {}

	/**
	 * @throws NotFound
	 * @throws StreamException
	 */
	public function __invoke(ServerRequestInterface $request, string $path): ResponseInterface
	{
		$file = $this->media->fromUrl("{$this->config->url}/{$path}")
			?? throw new NotFound(sprintf('There is no media file "%s".', $path));

		$headers = ['Cache-Control' => Response::fileCaching($request), 'X-Content-Type-Options' => 'nosniff'];

		if ($file->mime === 'image/svg+xml') {
			$headers['Content-Security-Policy'] = 'sandbox';
		}

		return Response::file($file->path, $file->mime, $headers, $request->getHeaderLine('Range'));
	}
}
