<?php

/**
 * Media usage.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media;

use Blush\Content\ContentRepository;
use Blush\Content\Source\ContentSource;
use Blush\Content\Source\UnreadableSource;
use Blush\Support\UrlPath;

/**
 * Finds the entries that use a media file (D-407), so deleting it can say
 * what would break: every content document whose text names the file by
 * any address the resolver takes for it (the media URL, `/user/media`,
 * or `user/media`, written plainly or encoded), in its body or its front
 * matter. It reads the documents, so it's for one file at a time.
 */
final readonly class MediaUsage
{
	public function __construct(
		private ContentSource $source,
		private ContentRepository $content,
		private MediaConfig $config
	) {}

	/**
	 * The entries that use a file, by its path in `user/media`
	 * (`2026/10/photo.jpg`), each with its `path` (its document's path),
	 * `title` (the document's path when it isn't indexed), and `type`
	 * (the type's singular label, or `''`).
	 *
	 * @return list<array{path: string, title: string, type: string}>
	 */
	public function entries(string $relative): array
	{
		$relative = trim($relative, '/');

		if ($relative === '') {
			return [];
		}

		$names   = array_unique([$relative, UrlPath::encode($relative)]);
		$prefix  = '(?:' . preg_quote($this->config->url, '#') . '|/?user/media)/';
		$pattern = '#' . $prefix . '(?:' . implode('|', array_map(static fn (string $name): string => preg_quote($name, '#'), $names)) . ')(?![A-Za-z0-9._%~-])#';
		$found   = [];

		try {
			$files = $this->source->files();
		} catch (UnreadableSource) {
			return [];
		}

		foreach ($files as $file) {
			try {
				$text = $this->source->read($file->path);
			} catch (UnreadableSource) {
				continue;
			}

			if (preg_match($pattern, $text) !== 1) {
				continue;
			}

			$entry   = $this->content->findPath($file->path);
			$found[] = [
				'path'  => $file->path,
				'title' => $entry === null || $entry->title === '' ? $file->path : $entry->title,
				'type'  => $entry?->type->labels->singular ?? ''
			];
		}

		return $found;
	}
}
