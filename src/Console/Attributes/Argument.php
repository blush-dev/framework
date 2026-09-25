<?php

/**
 * Argument attribute.
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
 * Marks a command's `__invoke()` parameter as a positional argument. The
 * parameter's type and default decide how the value is parsed and whether
 * it's required; a variadic parameter collects every remaining argument.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Argument
{
	public function __construct(public string $description = '')
	{}
}
