<?php

/**
 * Extension-style tool fixture.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Tool;

use Override;
use Blush\Tool\Tool;
use Blush\Tool\ToolAnnotations;
use Blush\Tool\ToolContext;
use Blush\Tool\ToolResult;

final class GreetTool extends Tool
{
	#[Override]
	public function title(): string
	{
		return 'Greet';
	}

	#[Override]
	public function description(): string
	{
		return 'Says hello.';
	}

	#[Override]
	public function capability(): string
	{
		return 'shop.greet';
	}

	#[Override]
	public function annotations(): ToolAnnotations
	{
		return ToolAnnotations::writes();
	}

	#[Override]
	public function confirm(): string
	{
		return 'Say hello?';
	}

	#[Override]
	public function handle(array $input, ToolContext $context): ToolResult
	{
		return ToolResult::done('Hello.', ['from PHP']);
	}
}
