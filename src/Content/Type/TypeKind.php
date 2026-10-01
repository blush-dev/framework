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
	case Authors    = 'authors';

	/**
	 * Returns the options a kind's definitions may use, beyond `name` and
	 * `kind`.
	 *
	 * @return list<string>
	 */
	public function options(): array
	{
		return match ($this) {
			self::Collection => ['folder', 'urls', 'listing', 'feed', 'dateArchives', 'public', 'sitemap', 'authors', 'fields', 'closed', 'labels', 'description', 'icon'],
			self::Taxonomy   => ['folder', 'types', 'field', 'aliases', 'hierarchical', 'urls', 'listing', 'termListing', 'feed', 'public', 'sitemap', 'authors', 'fields', 'closed', 'labels', 'description', 'icon'],
			self::Pages      => ['folder', 'public', 'sitemap', 'authors', 'fields', 'closed', 'labels', 'description', 'icon'],
			self::Authors    => ['folder', 'field', 'aliases', 'public', 'fields', 'closed', 'labels', 'description', 'icon']
		};
	}
}
