<?php

/**
 * Link report.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Relation;

/**
 * What building the links found (`LinkBuilder`): the graph, the values
 * that link to nothing, the entries whose two forms (D-589) Blush
 * would write differently, which a fixing tool rewrites, and each
 * entry's terms and credits as the index has always kept them (D-590).
 */
final readonly class LinkReport
{
	/**
	 * @param RelationGraph                          $graph    Every link.
	 * @param list<RelationProblem>                  $problems Values that link to nothing, and why.
	 * @param array<string, array<string, Resolution>> $stale  Resolutions to write, by source path and relation name.
	 * @param array<string, array<string, list<string>>> $terms Written term and profile slugs by source path and index key.
	 */
	public function __construct(
		public RelationGraph $graph,
		public array $problems = [],
		public array $stale = [],
		public array $terms = []
	) {}
}
