<?php

/**
 * Set status tool.
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
use Blush\Content\Editing\EntryEditor;
use Blush\Tool\ToolContext;
use Blush\Tool\ToolEntries;
use Blush\Tool\Tool;
use Blush\Tool\ToolAnnotations;
use Blush\Tool\ToolError;
use Blush\Tool\ToolResult;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * `set_status` (D-756): the only tool that publishes, unpublishes, or
 * schedules, so a client can ask the person first. Publishing needs the
 * entry's required fields and relationships in order.
 */
final class SetStatus extends Tool
{
	public function __construct(
		private readonly ToolEntries $entries,
		private readonly Messages $messages
	) {}

	#[Override]
	public function title(): Text
	{
		return $this->messages->t('tool.set_status.title', 'Publish, Unpublish, or Schedule');
	}

	#[Override]
	public function description(): Text
	{
		return $this->messages->t('tool.set_status.description', 'Changes whether an entry is live: "published" puts it on the site now, "draft" takes it off, and "scheduled" puts it on at a future "published" date. Only use it when the person asked to publish or unpublish. Needs the revision get_entry answered.');
	}

	#[Override]
	public function input(): array
	{
		return [
			'type'                 => 'object',
			'properties'           => [
				'id'        => ['type' => 'string'],
				'revision'  => ['type' => 'string', 'description' => 'The revision get_entry answered.'],
				'status'    => ['type' => 'string', 'enum' => EntryEditor::STATUSES],
				'published' => ['type' => 'string', 'description' => 'For "scheduled", the future date and time, such as 2026-11-01 09:00.']
			],
			'required'             => ['id', 'revision', 'status'],
			'additionalProperties' => false
		];
	}

	#[Override]
	public function capability(): ContentAction
	{
		return ContentAction::Publish;
	}

	#[Override]
	public function annotations(): ToolAnnotations
	{
		return ToolAnnotations::writes(idempotent: true);
	}

	#[Override]
	public function handle(array $input, ToolContext $context): ToolResult
	{
		$entry  = $this->entries->find($context, self::text($input, 'id'));
		$status = self::text($input, 'status');

		if ($status === 'scheduled' && ! is_string($input['published'] ?? null)) {
			throw new ToolError((string) $this->messages->t('tool.set_status.needs_date', 'Scheduling needs a future "published" date.'));
		}

		try {
			$updated = $context->entries->setStatus($context->actor, $entry, $status, self::text($input, 'revision'), self::optional($input, 'published'));
		} catch (EditRefused $refused) {
			throw Fields::refused($refused, $this->messages);
		}

		return new ToolResult($this->entries->stub($updated), match ($status) {
			'published' => $this->messages->t('tool.set_status.published', 'Published “{title}”.', title: $updated->title),
			'scheduled' => $this->messages->t('tool.set_status.scheduled', 'Scheduled “{title}”.', title: $updated->title),
			default     => $this->messages->t('tool.set_status.draft', 'Moved “{title}” to draft.', title: $updated->title)
		});
	}
}
