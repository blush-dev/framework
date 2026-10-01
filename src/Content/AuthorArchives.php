<?php

/**
 * Author archives.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content;

use Blush\Content\Entry\Entry;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;

/**
 * Finds who has an archive in a type (D-329): the published, routable
 * authors that at least one listed entry of the type credits, real or
 * virtual, by name. The authors list, the sitemap, and static export
 * all list the same authors.
 */
final readonly class AuthorArchives
{
	public function __construct(
		private ContentRepository $content,
		private ContentTypes $types
	) {}

	/**
	 * Returns the authors with an archive in a type, by name.
	 *
	 * @return list<Entry>
	 */
	public function authors(ContentType $type): array
	{
		$authors = $this->types->authors();

		if ($authors === null) {
			return [];
		}

		$listed = [];

		foreach ($this->content->termCounts($authors->name, $this->content->query()->type($type->name)) as $slug => $count) {
			$author = $count > 0 ? $this->content->term($authors->name, (string) $slug) : null;

			if ($author !== null && $author->isPublished() && $author->isRoutable()) {
				$listed[] = $author;
			}
		}

		usort($listed, static fn (Entry $a, Entry $b): int => strnatcasecmp($a->title, $b->title));

		return $listed;
	}
}
