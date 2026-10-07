<?php

/**
 * Relation problem.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Relation;

/**
 * Something wrong with an entry's links, for `content:lint`, Site
 * Health, and the admin's saves.
 */
final readonly class RelationProblem
{
	/**
	 * @param ProblemKind $kind    What's wrong.
	 * @param string      $key     The relation's key on the source's type (`movie.actors`).
	 * @param string      $source  The source entry's id.
	 * @param string      $value   The written value or id it's about, or `''`.
	 * @param string      $message What's wrong, as a sentence.
	 */
	public function __construct(
		public ProblemKind $kind,
		public string $key,
		public string $source,
		public string $value,
		public string $message
	) {}
}
