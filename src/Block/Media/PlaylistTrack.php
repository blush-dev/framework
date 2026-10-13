<?php

/**
 * Playlist track.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Block\Media;

/**
 * A playlist's track (D-708), as its row says it: the file's URL, its
 * title (HTML), who made it and from what ("Artist · Album"), and its
 * artwork's URL, each `''` when it has none.
 */
final readonly class PlaylistTrack
{
	public function __construct(
		public string $src,
		public string $title = '',
		public string $by = '',
		public string $art = ''
	) {}
}
