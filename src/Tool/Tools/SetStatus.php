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
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Tool\ToolContext;
use Blush\Tool\ToolEntries;
use Blush\Tool\Tool;
use Blush\Tool\ToolAnnotations;
use Blush\Tool\ToolError;
use Blush\Tool\ToolResult;
use Blush\Translation\Messages;
use Blush\Translation\Text;
use Blush\Workflow\Status;
use Blush\Workflow\Transition;
use Blush\Workflow\Workflows;

/**
 * `set_status` (D-756): the only tool that changes an entry's status
 * (publishing, unpublishing, scheduling, or another transition of its
 * type's workflow, D-851), so a client can ask the person first. It
 * takes a `status`, moved to by the transition to it, or a `transition`
 * by name. Going live needs the entry's required fields and
 * relationships in order. It's offered to an account that may edit and
 * publish, or edit and make a transition of its own (a site's Submit for
 * Review, say).
 */
final class SetStatus extends Tool
{
	public function __construct(
		private readonly ToolEntries $entries,
		private readonly Messages $messages,
		private readonly ContentTypes $types,
		private readonly Workflows $workflows
	) {}

	#[Override]
	public function title(): Text
	{
		return $this->messages->t('tool.set_status.title', 'Change Status');
	}

	#[Override]
	public function description(): Text
	{
		return $this->messages->t('tool.set_status.description', 'Changes an entry\'s status, by a transition of its type\'s workflow (get_type lists them): send "status" ("published" puts it on the site now, "draft" takes it off, "scheduled" puts it on at a future "date") or a "transition" by name. Only use it when the person asked for the change. Needs the revision get_entry answered.');
	}

	#[Override]
	public function input(): array
	{
		return [
			'type'                 => 'object',
			'properties'           => [
				'id'         => ['type' => 'string'],
				'revision'   => ['type' => 'string', 'description' => 'The revision get_entry answered.'],
				'status'     => ['type' => 'string', 'description' => 'A status of the type\'s workflow, or "scheduled".'],
				'transition' => ['type' => 'string', 'description' => 'A transition of the type\'s workflow, by name, in place of "status".'],
				'date'       => ['type' => 'string', 'description' => 'For "scheduled", the future date and time, such as 2026-11-01 09:00.']
			],
			'required'             => ['id', 'revision'],
			'additionalProperties' => false
		];
	}

	#[Override]
	public function capability(): ContentAction
	{
		return ContentAction::Edit;
	}

	#[Override]
	public function allows(ToolContext $context): bool
	{
		if (! $context->can(ContentAction::Edit)) {
			return false;
		}

		return $context->can(ContentAction::Publish) || array_any(
			$this->types->all(),
			fn (ContentType $type): bool => array_any($this->workflows->for($type)->transitions, static fn (Transition $transition): bool => $transition->capability && $context->can($transition->capabilityOn($type->name)))
		);
	}

	#[Override]
	public function annotations(): ToolAnnotations
	{
		return ToolAnnotations::writes(idempotent: true);
	}

	#[Override]
	public function handle(array $input, ToolContext $context): ToolResult
	{
		$entry      = $this->entries->find($context, self::text($input, 'id'));
		$status     = self::optional($input, 'status');
		$transition = self::optional($input, 'transition');

		if (($status === null) === ($transition === null)) {
			throw new ToolError((string) $this->messages->t('tool.set_status.one_of', 'Send a "status" or a "transition", not both.'));
		}

		if ($status === Status::SCHEDULED && ! is_string($input['date'] ?? null)) {
			throw new ToolError((string) $this->messages->t('tool.set_status.needs_date', 'Scheduling needs a future "date".'));
		}

		try {
			$updated = $transition === null
				? $context->entries->setStatus($context->actor, $entry, (string) $status, self::text($input, 'revision'), self::optional($input, 'date'))
				: $context->entries->transition($context->actor, $entry, $transition, self::text($input, 'revision'), self::optional($input, 'date'));
		} catch (EditRefused $refused) {
			throw Fields::refused($refused, $this->messages);
		}

		return new ToolResult($this->entries->stub($updated), match ($updated->status->name) {
			Status::PUBLISHED => $this->messages->t('tool.set_status.published', 'Published “{title}”.', title: $updated->title),
			Status::SCHEDULED => $this->messages->t('tool.set_status.scheduled', 'Scheduled “{title}”.', title: $updated->title),
			Status::DRAFT     => $this->messages->t('tool.set_status.draft', 'Moved “{title}” to draft.', title: $updated->title),
			default           => $this->messages->t('tool.set_status.moved', 'Moved “{title}” to {status}.', title: $updated->title, status: (string) $updated->status->label)
		});
	}
}
