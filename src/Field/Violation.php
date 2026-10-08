<?php

/**
 * Schema violation.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Field;

/**
 * One problem found while resolving data against a schema: a value that
 * doesn't fit its field, a missing required field, an undeclared key, or a
 * 1.x alias in use. Its `kind`, when it has one, says what kind of
 * problem it is (D-612).
 */
final readonly class Violation
{
	public function __construct(
		public string $field,
		public string $message,
		public Severity $severity = Severity::Error,
		public ?ViolationKind $kind = null
	) {}

	/**
	 * Returns the violation as `field: message`.
	 */
	public function __toString(): string
	{
		return "{$this->field}: {$this->message}";
	}
}
