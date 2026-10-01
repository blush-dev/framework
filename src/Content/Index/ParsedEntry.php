<?php

/**
 * Parsed entry.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Index;

use Blush\Field\Violation;

/**
 * A content file turned into an index record, with the problems its front
 * matter has. Bad values never stop a file from being indexed (D-084);
 * they're dropped from the record and reported here for `content:lint`.
 */
final readonly class ParsedEntry
{
	/**
	 * @param list<Violation> $violations
	 */
	public function __construct(
		public IndexRecord $record,
		public array $violations = []
	) {}
}
