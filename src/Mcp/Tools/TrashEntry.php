<?php

/**
 * Trash entry tool.
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
use Blush\Content\Entry\Entry;
use Blush\Content\Relation\Referrers;
use Blush\Mcp\McpContext;
use Blush\Mcp\McpEntries;
use Blush\Mcp\Tool;
use Blush\Mcp\ToolAnnotations;
use Blush\Mcp\ToolError;
use Blush\Mcp\ToolResult;

/**
 * `trash_entry` (D-756): moves an entry to the trash, a status (D-484),
 * so it can be restored. Live entries that link to it are named first
 * (D-608), and it's trashed only once that's confirmed. Nothing is ever
 * deleted for good through MCP.
 */
final class TrashEntry extends Tool
{
	public function __construct(
		private readonly McpEntries $entries,
		private readonly Referrers $referrers
	) {}

	#[Override]
	public function title(): string
	{
		return 'Move an Entry to the Trash';
	}

	#[Override]
	public function description(): string
	{
		return 'Moves an entry to the trash, off the site; restore_entry brings it back. When live entries link to it, it names them and does nothing until it\'s sent again with confirm: true. Needs the revision get_entry answered.';
	}

	#[Override]
	public function input(): array
	{
		return [
			'type'                 => 'object',
			'properties'           => [
				'id'       => ['type' => 'string'],
				'revision' => ['type' => 'string', 'description' => 'The revision get_entry answered.'],
				'confirm'  => ['type' => 'boolean', 'description' => 'Trash it even though live entries link to it.']
			],
			'required'             => ['id', 'revision'],
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
		return ToolAnnotations::writes(destructive: true);
	}

	#[Override]
	public function handle(array $input, McpContext $context): ToolResult
	{
		$entry = $this->entries->find($context, self::text($input, 'id'));

		if (($input['confirm'] ?? false) !== true) {
			$live = array_values(array_filter(
				array_column($this->referrers->sources($entry), 'entry'),
				static fn (Entry $source): bool => $source->isPublished()
			));

			if ($live !== []) {
				throw new ToolError(sprintf(
					'%d live entr%s link to “%s”: %s. Trashing it takes it off the site, and those links stop working. Send confirm: true to trash it anyway.',
					count($live),
					count($live) === 1 ? 'y' : 'ies',
					$entry->title,
					implode(', ', array_map(static fn (Entry $source): string => "“{$source->title}”", array_slice($live, 0, 8))) . (count($live) > 8 ? ', and more' : '')
				));
			}
		}

		try {
			[$trashed] = $context->entries->trash($context->actor, $entry, self::text($input, 'revision'));
		} catch (EditRefused $refused) {
			throw Fields::refused($refused);
		}

		return new ToolResult($this->entries->stub($trashed), sprintf('Moved “%s” to the trash; restore_entry brings it back.', $entry->title));
	}
}
