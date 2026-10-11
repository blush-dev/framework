<?php

/**
 * A plugin's tool that names no capability.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Mcp;

use Override;
use Blush\Tool\ToolContext;
use Blush\Tool\Tool;
use Blush\Tool\ToolResult;

final class OpenTool extends Tool
{
	#[Override]
	public function title(): string
	{
		return 'Open';
	}

	#[Override]
	public function description(): string
	{
		return 'Names no capability, so it is never offered.';
	}

	#[Override]
	public function input(): array
	{
		return ['type' => 'object'];
	}

	#[Override]
	public function handle(array $input, ToolContext $context): ToolResult
	{
		return new ToolResult(['ok' => true]);
	}
}
