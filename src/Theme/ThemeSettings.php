<?php

/**
 * Theme settings.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Theme;

use Blush\Content\Schema\Schema;
use Blush\Content\Schema\Violation;

/**
 * A theme chain's resolved settings: typed values (the site's, else the
 * definitions' defaults), and the problems found resolving them, for
 * `theme:check`.
 */
final readonly class ThemeSettings
{
	/**
	 * @param array<string, mixed> $values
	 * @param list<Violation>      $violations
	 */
	public function __construct(
		public Schema $schema = new Schema(),
		public array $values = [],
		public array $violations = []
	) {}

	/**
	 * Returns a setting's value, or a default.
	 */
	public function get(string $name, mixed $default = null): mixed
	{
		return $this->values[$name] ?? $default;
	}
}
