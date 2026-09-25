<?php

/**
 * Schema result.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Schema;

/**
 * The outcome of resolving data against a schema: normalized values keyed
 * by canonical field name, the undeclared keys as they were written, and
 * any violations.
 */
final readonly class SchemaResult
{
	/**
	 * @param array<string, mixed> $values     Normalized, exportable values.
	 * @param array<string, mixed> $extra      Undeclared keys and their raw values (D-081).
	 * @param list<Violation>      $violations
	 */
	public function __construct(
		public array $values = [],
		public array $extra = [],
		public array $violations = []
	) {}

	/**
	 * Returns whether any violation is an error.
	 */
	public function hasErrors(): bool
	{
		return array_any($this->violations, static fn (Violation $violation): bool => $violation->severity === Severity::Error);
	}

	/**
	 * Returns the violations at or above a severity: errors only, errors
	 * and warnings, or everything.
	 *
	 * @return list<Violation>
	 */
	public function violations(Severity $threshold = Severity::Notice): array
	{
		$ranks = [Severity::Error->value => 0, Severity::Warning->value => 1, Severity::Notice->value => 2];
		$limit = $ranks[$threshold->value];

		return array_values(array_filter(
			$this->violations,
			static fn (Violation $violation): bool => $ranks[$violation->severity->value] <= $limit
		));
	}
}
