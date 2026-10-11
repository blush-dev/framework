<?php

/**
 * A fixture extension's tool.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Admin\Recipes;

use Override;
use Blush\Tool\Tool;
use Blush\Tool\ToolAnnotations;
use Blush\Tool\ToolContext;
use Blush\Tool\ToolResult;

final class ImportTool extends Tool
{
	#[Override]
	public function title(): string
	{
		return 'Import Recipes';
	}

	#[Override]
	public function description(): string
	{
		return 'Imports recipes.';
	}

	#[Override]
	public function capability(): string
	{
		return 'site.settings';
	}

	#[Override]
	public function annotations(): ToolAnnotations
	{
		return ToolAnnotations::writes();
	}

	#[Override]
	public function handle(array $input, ToolContext $context): ToolResult
	{
		return ToolResult::done('Imported.');
	}
}
