<?php

/**
 * Theme report.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Theme;

use Blush\Field\Severity;
use Blush\Field\Violation;

/**
 * What `ThemeChecker` found: violations whose field names the area
 * checked (`manifest`, `tokens`, `contrast`, `layout`, …).
 */
final readonly class ThemeReport
{
	/**
	 * @param list<Violation> $violations
	 */
	public function __construct(
		public string $theme,
		public array $violations = []
	) {}

	/**
	 * Returns the violations with a severity.
	 *
	 * @return list<Violation>
	 */
	public function with(Severity $severity): array
	{
		return array_values(array_filter($this->violations, static fn (Violation $violation): bool => $violation->severity === $severity));
	}

	/**
	 * Returns whether any violation is an error.
	 */
	public function hasErrors(): bool
	{
		return $this->with(Severity::Error) !== [];
	}
}
