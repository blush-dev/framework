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
	case Pages      = 'pages';
	case Profiles   = 'profiles';

	/**
	 * Returns the options a kind's definitions may use, beyond `name` and
	 * `kind`.
	 *
	 * @return list<string>
	 */
	public function options(): array
	{
		return match ($this) {
			self::Collection => ['folder', 'urls', 'listing', 'feed', 'dateArchives', 'public', 'sitemap', 'people', 'fields', 'closed', 'labels', 'description', 'icon'],
			self::Taxonomy   => ['folder', 'types', 'field', 'aliases', 'hierarchical', 'urls', 'listing', 'termListing', 'feed', 'public', 'sitemap', 'people', 'fields', 'closed', 'labels', 'description', 'icon'],
			self::Pages      => ['folder', 'public', 'sitemap', 'people', 'fields', 'closed', 'labels', 'description', 'icon'],
			self::Profiles   => ['folder', 'urls', 'listing', 'feed', 'public', 'sitemap', 'fields', 'closed', 'labels', 'description', 'icon']
		};
	}

	/**
	 * Returns whether a type of this kind defined in code may be changed
	 * by a data file over it (D-349). The site has one pages type and
	 * one profiles type, so those stay as the code defines them.
	 */
	public function isOverridable(): bool
	{
		return $this === self::Collection || $this === self::Taxonomy;
	}
}
