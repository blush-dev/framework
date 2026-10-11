<?php

/**
 * Restore entry tool.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Mcp\Tools;

use Override;
use Blush\Auth\ContentAction;
use Blush\Content\Editing\EditRefused;
use Blush\Content\Status;
use Blush\Mcp\McpContext;
use Blush\Mcp\McpEntries;
use Blush\Mcp\Tool;
use Blush\Mcp\ToolAnnotations;
use Blush\Mcp\ToolResult;

/**
 * `restore_entry` (D-756): brings an entry back from the trash as a
 * draft (D-237), so it's never republished without someone choosing to.
 */
final class RestoreEntry extends Tool
{
	public function __construct(private readonly McpEntries $entries)
	{}

	#[Override]
	public function title(): string
	{
		return 'Restore an Entry';
	}

	#[Override]
	public function description(): string
	{
		return 'Brings an entry back from the trash as a draft. search_entries with status ["trash"] finds what\'s there.';
	}

	#[Override]
	public function input(): array
	{
		return [
			'type'                 => 'object',
			'properties'           => ['id' => ['type' => 'string']],
			'required'             => ['id'],
			'additionalProperties' => false
		];
	}

	#[Override]
	public function capability(): ContentAction
	{
		return ContentAction::Delete;
	}

	#[Override]
	public function annotations(): ToolAnnotations
	{
		return ToolAnnotations::writes(idempotent: true);
	}

	#[Override]
	public function handle(array $input, McpContext $context): ToolResult
	{
		$entry = $this->entries->find($context, self::text($input, 'id'));

		try {
			$restored = $context->entries->restore($context->actor, $entry, Status::Draft);
		} catch (EditRefused $refused) {
			throw Fields::refused($refused);
		}

		return new ToolResult($this->entries->stub($restored), sprintf('Restored “%s” as a draft.', $restored->title));
	}
}
