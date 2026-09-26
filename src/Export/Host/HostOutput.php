<?php

/**
 * Host output.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Export\Host;

/**
 * A host format's files, by path relative to the export folder, and its
 * notices about what they leave out.
 */
final readonly class HostOutput
{
	/**
	 * @param array<string, string> $files
	 * @param list<string>          $notices
	 */
	public function __construct(
		public array $files,
		public array $notices = []
	) {}
}
