<?php

/**
 * Relation kind.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Relation;

/**
 * What a relation is for (D-585), which sets what it does by default:
 *
 * - `Classify`: an entry filed under terms, such as a post's categories.
 *   Terms may be created as they're typed, and a term's page lists what
 *   uses it.
 * - `Credit`: the people an entry credits, such as its authors, in
 *   order, the first being the lead.
 * - `Reference`: any other entries, such as a movie's actors.
 * - `Parent`: an entry's place under another of its own type, filed
 *   like any relation (D-591): written as `parent` (a tree's folder is
 *   its written form) with its id in `refs`. Hierarchical, so a cycle
 *   is refused (D-587).
 * - `Translation`: a translation's original (`translation_of`, or the
 *   file it shares a name with, D-455, D-511). Structural: Blush files
 *   it by its own rules (an id, a file name), never in `refs`.
 *
 * Both point from one type to itself, one target each.
 */
enum RelationKind: string
{
	case Classify    = 'classify';
	case Credit      = 'credit';
	case Reference   = 'reference';
	case Parent      = 'parent';
	case Translation = 'translation';

	/**
	 * Returns whether Blush files the relation by its own rules rather
	 * than as a list of written values with their `refs`.
	 */
	public function isStructural(): bool
	{
		return $this === self::Translation;
	}

	/**
	 * Returns whether the relation points from one type to itself, one
	 * target each.
	 */
	public function isWithinType(): bool
	{
		return $this === self::Parent || $this === self::Translation;
	}

	/**
	 * Returns whether the relation forms a hierarchy, where a cycle is
	 * refused (D-587).
	 */
	public function isHierarchical(): bool
	{
		return $this === self::Parent;
	}
}
