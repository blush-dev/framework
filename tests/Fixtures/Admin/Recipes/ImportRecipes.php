<?php

/**
 * A fixture extension's command.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Admin\Recipes;

use Blush\Console\Attributes\Command;
use Blush\Console\ExitCode;

#[Command('recipes:import', 'Import recipes.')]
final readonly class ImportRecipes
{
	public function __invoke(): ExitCode
	{
		return ExitCode::Success;
	}
}
