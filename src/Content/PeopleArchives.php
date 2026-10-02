<?php

/**
 * People archives.
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
use Blush\Content\Type\PeopleField;

/**
 * Finds who has a page (D-351), so the lists, the sitemap, and static
 * export agree:
 *
 * - `credited()`: the profiles a people field of a type credits on at
 *   least one listed entry, real or virtual, by name. Each has an
 *   archive under the field's word.
 * - `profiles()`: every published profile with a file, and every
 *   virtual one a listed entry credits, by name. Each has a page of its
 *   own when the profiles type has URLs.
 *
 * Only published, routable profiles count.
 */
final readonly class PeopleArchives
{
	public function __construct(
		private ContentRepository $content,
		private ContentTypes $types
	) {}

	/**
	 * Returns the profiles a type's people field credits, by name.
	 *
	 * @return list<Entry>
	 */
	public function credited(ContentType $type, PeopleField $field): array
	{
		$profiles = $this->types->profiles();

		if ($profiles === null) {
			return [];
		}

		$listed = [];

		foreach ($this->content->termCounts($field->termKey($profiles->name), $this->content->query()->type($type->name)) as $slug => $count) {
			$profile = $count > 0 ? $this->visible($this->content->term($profiles->name, (string) $slug)) : null;

			if ($profile !== null) {
				$listed[] = $profile;
			}
		}

		return self::byName($listed);
	}

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
				$listed[$profile->slug] = $profile;
			}
		}

		$crediting = array_keys($this->types->crediting());

		foreach ($crediting === [] ? [] : $this->content->termCounts($profiles->name, $this->content->query()->type(...$crediting)) as $slug => $count) {
			$profile = $count > 0 && ! isset($listed[(string) $slug]) ? $this->visible($this->content->term($profiles->name, (string) $slug)) : null;

			if ($profile !== null) {
				$listed[$profile->slug] = $profile;
			}
		}

		return self::byName(array_values($listed));
	}

	/**
	 * Returns a profile when it's published and routable.
	 */
	private function visible(?Entry $profile): ?Entry
	{
		return $profile !== null && $profile->isPublished() && $profile->isRoutable() ? $profile : null;
	}

	/**
	 * Sorts profiles by name (D-304).
	 *
	 * @param  list<Entry> $profiles
	 * @return list<Entry>
	 */
	private static function byName(array $profiles): array
	{
		usort($profiles, static fn (Entry $a, Entry $b): int => strnatcasecmp($a->title, $b->title));

		return $profiles;
	}
}
