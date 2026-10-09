<?php

/**
 * Entry values.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Record;

use Blush\Support\Slug;

/**
 * Values core works out from an entry's front matter, the same for
 * every driver (D-649): `slugs`, each value written as slugs, for the
 * 1.x queries that compare values that way (`meta_key`/`meta_value`,
 * and terms written in front matter without refs, D-078).
 */
final class EntryValues
{
	/**
	 * Returns each front matter key's value as slugs: text and numbers
	 * as one slug, lists as each item's, and other values (true, false,
	 * maps) as none. Empty values (`null`, `''`, `[]`) are left out, so a
	 * key is here exactly when the entry has a value for it.
	 *
	 * @param  array<array-key, mixed> $frontMatter
	 * @return array<string, list<string>>
	 */
	public static function slugs(array $frontMatter): array
	{
		$slugs = [];

		foreach ($frontMatter as $key => $value) {
			if ($value === null || $value === '' || $value === []) {
				continue;
			}

			$slugs[(string) $key] = [];

			foreach (is_array($value) && array_is_list($value) ? $value : [$value] as $item) {
				if (is_string($item) || is_int($item) || is_float($item)) {
					$slugs[(string) $key][] = Slug::from((string) $item);
				}
			}
		}

		return $slugs;
	}
}
