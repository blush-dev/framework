<?php

/**
 * Relation control.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Relation;

/**
 * How the admin's editor picks a relation's targets (D-585, D-599),
 * named by the server: a relation sets its own `control`, or gets its
 * shape's (`Relation::control()`). A control its shape can't draw (a
 * tree of a type that doesn't nest, a select for several) falls back to
 * the shape's.
 *
 * - `Tree`: a nesting collection's terms, as checkboxes in their tree.
 * - `Tokens`: chips, with a search that may write a new target.
 * - `People`: credited profiles, in order, each with a mark and a name.
 * - `Select`: one target, from a list.
 * - `Cards`: chips, with a search whose results show each entry's image,
 *   status, and date, for picking among many entries.
 */
enum RelationControl: string
{
	case Tree   = 'tree';
	case Tokens = 'tokens';
	case People = 'people';
	case Select = 'select';
	case Cards  = 'cards';
}
