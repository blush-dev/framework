<?php

/**
 * Id taken.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Writer;

use RuntimeException;
use Blush\Core\BlushException;

/**
 * An entry in the trash can't come back as it is: another entry has its
 * id now (D-481), such as a copy made before it was trashed. Restoring
 * it with a new id, or leaving it in the trash, is the caller's choice.
 */
final class IdTaken extends RuntimeException implements BlushException
{
	/**
	 * @param string $id     The id both have.
	 * @param string $holder The path of the entry that has it now.
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $holder,
		string $message
	) {
		parent::__construct($message);
	}
}
