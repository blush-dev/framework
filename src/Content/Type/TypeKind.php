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

	/**
	 * Returns the options a kind's definitions may use, beyond `name` and
	 * `kind`.
	 *
	 * @return list<string>
	 */
	public function options(): array
	{
		return match ($this) {
			self::Collection => ['folder', 'urls', 'listing', 'feed', 'dateArchives', 'public', 'sitemap', 'fields', 'closed'],
			self::Taxonomy   => ['folder', 'types', 'field', 'aliases', 'urls', 'listing', 'termListing', 'feed', 'public', 'sitemap', 'fields', 'closed'],
			self::Pages      => ['folder', 'public', 'sitemap', 'fields', 'closed']
		};
	}
}
