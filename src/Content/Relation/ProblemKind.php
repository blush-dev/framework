<?php

/**
 * Relation problem kind.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Relation;

/**
 * What's wrong with an entry's links (D-585, D-587).
 */
enum ProblemKind: string
{
	/** A written value or id names no entry. */
	case Missing = 'missing';

	/** A written value names entries of more than one target type. */
	case Ambiguous = 'ambiguous';

	/** An id names an entry of a type the relation doesn't point to. */
	case WrongType = 'wrong-type';

	/** An entry names itself. */
	case SelfLink = 'self';

	/** A live entry has fewer targets than the relation's min. */
	case TooFew = 'too-few';

	/** An entry has more targets than the relation's max. */
	case TooMany = 'too-many';

	/** A hierarchical relation leads back to where it started. */
	case Cycle = 'cycle';

	/** More entries point at a target than the inverse's max. */
	case InverseLimit = 'inverse-limit';
}
