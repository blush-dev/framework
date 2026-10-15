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

namespace Blush\Tool\Tools;

use Override;
use Blush\Auth\ContentAction;
use Blush\Content\Editing\EditRefused;
use Blush\Tool\Tool;
use Blush\Tool\ToolAnnotations;
use Blush\Tool\ToolContext;
use Blush\Tool\ToolEntries;
use Blush\Tool\ToolResult;
use Blush\Translation\Messages;
use Blush\Translation\Text;
use Blush\Workflow\Status;

/**
 * `restore_entry` (D-756): brings an entry back from the trash as a
 * draft (D-237), so it's never republished without someone choosing to.
 */
final class RestoreEntry extends Tool
{
	public function __construct(
		private readonly ToolEntries $entries,
		private readonly Messages $messages
	) {}

	#[Override]
	public function title(): Text
	{
		return $this->messages->t('tool.restore_entry.title', 'Restore an Entry');
	}

	#[Override]
	public function description(): Text
	{
		return $this->messages->t('tool.restore_entry.description', 'Brings an entry back from the trash as a draft. search_entries with status ["trash"] finds what\'s there.');
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
	public function handle(array $input, ToolContext $context): ToolResult
	{
		$entry = $this->entries->find($context, self::text($input, 'id'));

		try {
			$restored = $context->entries->restore($context->actor, $entry, Status::DRAFT);
		} catch (EditRefused $refused) {
			throw Fields::refused($refused, $this->messages);
		}

		return new ToolResult($this->entries->stub($restored), $this->messages->t('tool.restore_entry.restored', 'Restored “{title}” as a draft.', title: $restored->title));
	}
}
