<?php

/**
 * Feed site URLs.
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
use Blush\Content\ProfileList;
use Blush\Content\Relation\Relation;
use Blush\Content\RelationArchives;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\Profiles;
use Blush\Routing\SiteUrl;
use Blush\Routing\UrlSource;

/**
 * Lists every feed (D-136, D-476): each public, routed type's
 * collection feed in every configured format, a term type's per-term
 * feeds for the terms listed entries reference, the feeds of each
 * relation archive's targets (D-596, D-602: a person's under a credit),
 * and each profile's feed (D-351).
 */
final readonly class FeedSiteUrls implements UrlSource
{
	public function __construct(
		private ContentRepository $content,
		private ContentTypes $types,
		private ContentUrls $urls,
		private FeedConfig $config,
		private ProfileList $profileList,
		private RelationArchives $relationArchives
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
				foreach ($this->profileList->profiles() as $profile) {
					foreach ($this->config->formats as $format) {
						$path = $this->urls->profileFeed($profile->slug, "single.feed{$format->routeSuffix()}");

						if ($path !== null) {
							yield new SiteUrl($path);
						}
					}
				}

				continue;
			}

			$terms  = $this->types->hasTermPages($type->name) ? array_map(strval(...), array_keys($this->content->termCounts($this->types->termKeys($type->name)))) : [];

			$related = array_map(fn (Relation $relation): array => [$relation, $this->relationArchives->linked($type, $relation)], array_values($this->types->relationArchives($type)));

			foreach ($this->config->formats as $format) {
				$key  = "collection.feed{$format->routeSuffix()}";
				$path = $this->urls->feed($type, $key);

				if ($path !== null) {
					yield new SiteUrl($path);
				}

				foreach ($terms as $term) {
					$path = $this->urls->feed($type, $key, $term);

					if ($path !== null) {
						yield new SiteUrl($path);
					}
				}

				foreach ($related as [$relation, $targets]) {
					foreach ($targets as $target) {
						$path = $this->urls->relatedFeed($type, $relation, $target->slug, $format->routeSuffix());

						if ($path !== null) {
							yield new SiteUrl($path);
						}
					}
				}
			}
		}
	}
}
