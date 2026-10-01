<?php

/**
 * Lint report.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Lint;

use Blush\Field\Severity;
use Blush\Field\Violation;

/**
 * The problems `Linter` found, by path: a content file's under
 * `user/content`, a media metadata file's from the site root (D-293).
 */
final readonly class LintReport
{
	/**
	 * @param int                            $checked  How many content files were checked.
	 * @param array<string, list<Violation>> $files    Violations by path.
	 * @param int                            $metadata How many media metadata files were checked.
	 */
	public function __construct(
		public int $checked = 0,
		public array $files = [],
		public int $metadata = 0
	) {}

	/**
	 * Returns the violations at or above a severity, by path, leaving out
	 * files with none.
	 *
	 * @return array<string, list<Violation>>
	 */
	public function violations(Severity $threshold = Severity::Notice): array
	{
		$ranks = [Severity::Error->value => 0, Severity::Warning->value => 1, Severity::Notice->value => 2];
		$files = [];

		foreach ($this->files as $path => $violations) {
			$kept = array_values(array_filter(
				$violations,
				static fn (Violation $violation): bool => $ranks[$violation->severity->value] <= $ranks[$threshold->value]
			));

			if ($kept !== []) {
				$files[$path] = $kept;
			}
		}

		return $files;
	}

	/**
	 * Returns how many violations have a severity.
	 */
	public function count(Severity $severity): int
	{
		$count = 0;

		foreach ($this->files as $violations) {
			$count += count(array_filter($violations, static fn (Violation $violation): bool => $violation->severity === $severity));
		}

		return $count;
	}

	/**
	 * Returns whether any violation is an error.
	 */
	public function hasErrors(): bool
	{
		return $this->count(Severity::Error) > 0;
	}
}
