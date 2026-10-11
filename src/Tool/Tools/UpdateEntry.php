<?php

/**
 * Update entry tool.
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
use Blush\Content\Editing\EntryEdit;
use Blush\Tool\ToolContext;
use Blush\Tool\ToolEntries;
use Blush\Tool\Tool;
use Blush\Tool\ToolAnnotations;
use Blush\Tool\ToolError;
use Blush\Tool\ToolResult;

/**
 * `update_entry` (D-756): changes only what it's given, at the revision
 * `get_entry` answered; a stale one is refused, never overwritten. A
 * field set to `null` is removed; a new slug or parent moves the entry,
 * leaving redirects from a published entry's old address, as the admin
 * does. Publishing is `set_status`'s.
 */
final class UpdateEntry extends Tool
{
	public function __construct(private readonly ToolEntries $entries)
	{}

	#[Override]
	public function title(): string
	{
		return 'Update an Entry';
	}

	#[Override]
	public function description(): string
	{
		return 'Changes an entry: only the fields given (null removes one), a whole new Markdown body, a new slug, or, for a page, a new parent page. Needs the revision get_entry answered; if someone changed it since, it\'s refused, so read it again. For a small change to the body, use edit_body instead. To publish or unpublish, use set_status.';
	}

	#[Override]
	public function input(): array
	{
		return [
			'type'                 => 'object',
			'properties'           => [
				'id'       => ['type' => 'string'],
				'revision' => ['type' => 'string', 'description' => 'The revision get_entry answered.'],
				'fields'   => ['type' => 'object', 'description' => 'Field values to change, by name; null removes one.'],
				'body'     => ['type' => 'string', 'description' => 'The whole new body, in Markdown.'],
				'slug'     => ['type' => 'string'],
				'parent'   => ['type' => 'string', 'description' => 'For a page of a tree, the slug path of the page to move it under, or "" for the top.'],
				'redirect' => ['type' => 'boolean', 'description' => 'Whether a published entry\'s old address redirects to its new one when it moves; true when left out.']
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
	public function annotations(): ToolAnnotations
	{
		return ToolAnnotations::writes(draftsSafe: true);
	}

	#[Override]
	public function handle(array $input, ToolContext $context): ToolResult
	{
		$entry  = $this->entries->find($context, self::text($input, 'id'));
		$set    = [];
		$remove = [];

		foreach (Fields::from($input['fields'] ?? []) as $name => $value) {
			if ($name === 'status') {
				throw new ToolError('Change the status with set_status, not update_entry.');
			}

			if ($value === null) {
				$remove[] = $name;
			} else {
				$set[$name] = $value;
			}
		}

		try {
			$updated = $context->entries->update($context->actor, $entry, new EntryEdit(
				set: $set,
				remove: $remove,
				body: self::optional($input, 'body'),
				slug: self::optional($input, 'slug'),
				parent: self::optional($input, 'parent'),
				redirect: ($input['redirect'] ?? true) === true
			), self::text($input, 'revision'));
		} catch (EditRefused $refused) {
			throw Fields::refused($refused);
		}

		return new ToolResult($this->entries->item($context, $updated), sprintf('Updated “%s”.', $updated->title));
	}
}
