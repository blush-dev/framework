<?php

/**
 * Feed export URLs.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Feed;

use Override;
use Blush\Content\ContentRepository;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\Taxonomy;
use Blush\Export\ExportUrl;
use Blush\Export\UrlSource;

/**
 * Lists every feed for static export (D-136): each public, routed type's
 * collection feed in every configured format, and a taxonomy's per-term
 * feeds for the terms listed entries reference.
 */
final readonly class FeedExportUrls implements UrlSource
{
	public function __construct(
		private ContentRepository $content,
		private ContentTypes $types,
		private ContentUrls $urls,
		private FeedConfig $config
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function urls(): iterable
	{
		foreach ($this->types->all() as $type) {
			if (! $type->public || ! $type->hasUrls() || ! $type->hasFeed()) {
				continue;
			}

			$terms = $type instanceof Taxonomy ? array_map(strval(...), array_keys($this->content->termCounts($type->name))) : [];

			foreach ($this->config->formats as $format) {
				$key  = "collection.feed{$format->routeSuffix()}";
				$path = $this->urls->feed($type, $key);

				if ($path !== null) {
					yield new ExportUrl($path);
				}

				foreach ($terms as $term) {
					$path = $this->urls->feed($type, $key, $term);

					if ($path !== null) {
						yield new ExportUrl($path);
					}
				}
			}
		}
	}
}
