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
use Blush\Content\PeopleArchives;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\Profiles;
use Blush\Content\Type\Taxonomy;
use Blush\Export\ExportUrl;
use Blush\Export\UrlSource;

/**
 * Lists every feed for static export (D-136): each public, routed type's
 * collection feed in every configured format, a taxonomy's per-term
 * feeds for the terms listed entries reference, the per-person feeds of
 * each people field with archives, and each profile's feed (D-351).
 */
final readonly class FeedExportUrls implements UrlSource
{
	public function __construct(
		private ContentRepository $content,
		private ContentTypes $types,
		private ContentUrls $urls,
		private FeedConfig $config,
		private PeopleArchives $archives
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

			if ($type instanceof Profiles) {
				foreach ($this->archives->profiles() as $profile) {
					foreach ($this->config->formats as $format) {
						$path = $this->urls->profileFeed($profile->slug, "single.feed{$format->routeSuffix()}");

						if ($path !== null) {
							yield new ExportUrl($path);
						}
					}
				}

				continue;
			}

			$terms  = $type instanceof Taxonomy ? array_map(strval(...), array_keys($this->content->termCounts($type->name))) : [];
			$people = [];

			foreach ($type->archivedPeople() as $field) {
				$people[] = [$field, $this->urls->hasArchive($type, $field) ? $this->archives->credited($type, $field) : []];
			}

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

				foreach ($people as [$field, $profiles]) {
					foreach ($profiles as $profile) {
						$path = $this->urls->personFeed($type, $field, $profile->slug, $format->routeSuffix());

						if ($path !== null) {
							yield new ExportUrl($path);
						}
					}
				}
			}
		}
	}
}
