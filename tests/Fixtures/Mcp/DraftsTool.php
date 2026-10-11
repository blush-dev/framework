<?php

/**
 * A plugin's tool that writes only drafts.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Mcp;

use Override;
use Blush\Auth\ContentAction;
use Blush\Mcp\McpContext;
use Blush\Mcp\Tool;
use Blush\Mcp\ToolAnnotations;
use Blush\Mcp\ToolResult;

final class DraftsTool extends Tool
{
	#[Override]
	public function title(): string
	{
		return 'Drafts';
	}

	#[Override]
	public function description(): string
	{
		return 'Writes drafts only.';
	}

	#[Override]
	public function input(): array
	{
		return ['type' => 'object'];
	}

	#[Override]
	public function capability(): ContentAction
	{
		return ContentAction::Edit;
	}

	#[Override]
	public function annotations(): ToolAnnotations
	{
		return ToolAnnotations::writes(draftsSafe: true);
	}

	#[Override]
	public function handle(array $input, McpContext $context): ToolResult
	{
		return new ToolResult(['ok' => true]);
	}
}
