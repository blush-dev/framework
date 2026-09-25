<?php

/**
 * Option attribute.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Attributes;

use Attribute;

/**
 * Marks a command's `__invoke()` parameter as an option. A `bool` parameter
 * is a flag, an `array` parameter is a repeatable option, and anything else
 * takes a value. The option name defaults to the parameter name in
 * kebab-case (`$dryRun` becomes `--dry-run`).
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Option
{
	/**
	 * @param ?string $short A single-character short name (`-f`).
	 * @param ?string $name  The long name, if not derived from the parameter.
	 */
	public function __construct(
		public string $description = '',
		public ?string $short = null,
		public ?string $name = null
	) {}
}
