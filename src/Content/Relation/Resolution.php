<?php

/**
 * Resolution.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Relation;

/**
 * What one entry's written values in one relation resolve to
 * (`LinkResolver`): its links, both forms as Blush writes them (D-589),
 * and anything wrong.
 */
final readonly class Resolution
{
	/**
	 * @param list<Link>            $links    The links, in order.
	 * @param list<string>          $written  The written form: each linked target's slug or path.
	 * @param array<string, string> $refs     The id form: ids by written value.
	 * @param list<RelationProblem> $problems Values that link to nothing, and why.
	 * @param bool                  $changed  Whether either form differs from the file's.
	 */
	public function __construct(
		public array $links,
		public array $written,
		public array $refs,
		public array $problems,
		public bool $changed
	) {}

	/**
	 * Returns the written form as front matter takes it: a list, or for
	 * a relation with one target, that target or `null`.
	 *
	 * @return list<string>|string|null
	 */
	public function value(Relation $relation): array|string|null
	{
		return $relation->multiple ? $this->written : $this->written[0] ?? null;
	}
}
