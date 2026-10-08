<?php

/**
 * Relation source interface.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Relation;

/**
 * Supplies relations from an extension or the site (D-585), such as
 * "related products" on a type the extension doesn't own. A provider
 * tags its source with `RelationSource::TAG` in its `TAGS`. The ones
 * Blush makes from content types (reference fields, parents,
 * translations) come first (`RelationCompiler`), and a
 * source's relation may not take a name or front matter key one of them
 * has on the same type.
 */
interface RelationSource
{
	/**
	 * The container tag for relation sources.
	 */
	public const string TAG = 'content.relations';

	/**
	 * Returns the relations.
	 *
	 * @return iterable<Relation>
	 */
	public function relations(): iterable;
}
