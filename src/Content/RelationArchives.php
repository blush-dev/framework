<?php

/**
 * Relation archives.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content;

use Blush\Content\Entry\Entry;
use Blush\Content\Relation\Relation;
use Blush\Content\Type\ContentType;

/**
 * Finds the targets with an archive under a type's relation (an inverse
 * `archive` word, D-596), so the list, the sitemap, feeds, and the site's
 * URLs agree: the published, routable entries at least one listed entry
 * of the type links to through the relation, by title. Each has an
 * archive under the relation's word (`/movies/actors/tom`).
 */
final readonly class RelationArchives
{
	public function __construct(private Entries $content)
	{}

	/**
	 * Returns the targets a type's relation links to, by title.
	 *
	 * @return list<Entry>
	 */
	public function linked(ContentType $type, Relation $relation): array
	{
		$key     = $relation->termKey();
		$targets = [];

		foreach ($key === null ? [] : $this->content->termCounts($key, $this->content->query()->type($type->name)) as $slug => $count) {
			$target = $count > 0 ? $this->content->named($relation->to[0], (string) $slug) : null;

			if ($target !== null && $target->isPublished() && $target->isRoutable()) {
				$targets[] = $target;
			}
		}

		usort($targets, static fn (Entry $a, Entry $b): int => strnatcasecmp($a->title, $b->title));

		return $targets;
	}
}
