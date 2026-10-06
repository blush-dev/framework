<?php

/**
 * Content type kind.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Type;

/**
 * The kinds of content type, as a data type's `kind` names them (D-157).
 * Each is a final `ContentType` class.
 */
enum TypeKind: string
{
	case Collection = 'collection';
	case Taxonomy   = 'taxonomy';
	case Tree       = 'tree';
	case Profiles   = 'profiles';

	/**
	 * Returns whether the kind's entries are listed in `llms.txt` unless
	 * a type says otherwise (D-398, D-401): collections and trees hold
	 * the writing; taxonomies' terms and profiles are often thin, so a
	 * site opts them in.
	 */
	public function inLlmsByDefault(): bool
	{
		return $this === self::Collection || $this === self::Tree;
	}

	/**
	 * Returns the options a kind's definitions may use, beyond `name` and
	 * `kind`.
	 *
	 * @return list<string>
	 */
	public function options(): array
	{
		return match ($this) {
			self::Collection => ['folder', 'filename', 'urls', 'listing', 'feed', 'dateArchives', 'public', 'sitemap', 'llms', 'people', 'fields', 'closed', 'labels', 'description', 'icon'],
			self::Taxonomy   => ['folder', 'filename', 'types', 'field', 'aliases', 'hierarchical', 'urls', 'listing', 'termListing', 'feed', 'public', 'sitemap', 'llms', 'people', 'fields', 'closed', 'labels', 'description', 'icon'],
			self::Tree       => ['folder', 'filename', 'public', 'sitemap', 'llms', 'people', 'fields', 'closed', 'labels', 'description', 'icon'],
			self::Profiles   => ['folder', 'filename', 'urls', 'listing', 'feed', 'public', 'sitemap', 'llms', 'fields', 'closed', 'labels', 'description', 'icon']
		};
	}
}
