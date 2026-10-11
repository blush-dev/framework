<?php

/**
 * A plugin's tool that writes more than drafts.
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
use Blush\Tool\ToolContext;
use Blush\Tool\Tool;
use Blush\Tool\ToolAnnotations;
use Blush\Tool\ToolResult;

final class WritingTool extends Tool
{
	#[Override]
	public function title(): string
	{
		return 'Writing';
	}

	#[Override]
	public function description(): string
	{
		return 'Writes, needing to edit entries.';
	}

	#[Override]
	public function input(): array
	{
		return ['type' => 'object', 'properties' => ['times' => ['type' => 'integer', 'maximum' => 3]], 'additionalProperties' => false];
	}

	#[Override]
	public function capability(): ContentAction
	{
		return ContentAction::Edit;
	}

	#[Override]
	public function annotations(): ToolAnnotations
	{
		return ToolAnnotations::writes();
	}

	#[Override]
	public function handle(array $input, ToolContext $context): ToolResult
	{
		return new ToolResult(['wrote' => $input['times'] ?? 1]);
	}
}
