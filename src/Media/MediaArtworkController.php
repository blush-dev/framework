<?php

/**
 * Media artwork controller.
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
use Blush\Http\Status;
use Blush\Media\Embedded\EmbeddedMetadataReader;

/**
 * Answers with the picture a sound or video in `user/media` carries,
 * such as its cover art (D-575), for the site's audio cards: `GET
 * {url}-artwork/{path}` (`MediaConfig::artworkUrl()`). It's read from
 * the file each time, as the admin's is (D-551), and is as public as
 * the file. 404 when the file isn't media or has none.
 */
final readonly class MediaArtworkController
{
	public function __construct(
		private MediaResolver $media,
		private MediaConfig $config,
		private EmbeddedMetadataReader $embedded
	) {}

	/**
	 * @throws NotFound
	 */
	public function __invoke(ServerRequestInterface $request, string $path): ResponseInterface
	{
		$file    = $this->media->fromUrl("{$this->config->url}/{$path}");
		$artwork = $file === null || ! in_array($file->type(), ['audio', 'video'], true) ? null : $this->embedded->artwork($file->path, $file->mime);

		if ($artwork === null) {
			throw new NotFound(sprintf('There is no artwork in "%s".', $path));
		}

		return new Response(Status::Ok, [
			'Content-Type'           => $artwork->mime,
			'Cache-Control'          => 'public, max-age=86400',
			'X-Content-Type-Options' => 'nosniff'
		], $artwork->bytes);
	}
}
