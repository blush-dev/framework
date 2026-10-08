<?php

/**
 * Profile list.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content;

use Blush\Content\Entry\Entry;
use Blush\Content\Type\ContentTypes;

/**
 * Finds every profile with a page of its own (D-351), by name, so the
 * sitemap, feeds, and the site's URLs agree. Only published, routable
 * profiles count. The archives under each type that credits them are
 * relation archives (`RelationArchives`, D-602).
 */
final readonly class ProfileList
{
	public function __construct(
		private ContentRepository $content,
		private ContentTypes $types
	) {}

	/**
	 * Returns every profile with a page of its own, by name.
	 *
	 * @return list<Entry>
	 */
	public function profiles(): array
	{
		$profiles = $this->types->profiles();

		if ($profiles === null) {
			return [];
		}

		$listed = [];

		foreach ($this->content->query()->type($profiles->name)->get() as $profile) {
			if ($profile->isRoutable()) {
				$listed[] = $profile;
			}
		}

		usort($listed, static fn (Entry $a, Entry $b): int => strnatcasecmp($a->title, $b->title));

		return $listed;
	}
}
