<?php

/**
 * Edit body tool.
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
use Blush\Tool\ToolContext;
use Blush\Tool\ToolEntries;
use Blush\Tool\Tool;
use Blush\Tool\ToolAnnotations;
use Blush\Tool\ToolResult;

/**
 * `edit_body` (D-756): replaces one exact passage of an entry's body,
 * found exactly once, for a small edit ("fix the typo in the third
 * paragraph") without sending the whole body back.
 */
final class EditBody extends Tool
{
	public function __construct(private readonly ToolEntries $entries)
	{}

	#[Override]
	public function title(): string
	{
		return 'Edit Part of a Body';
	}

	#[Override]
	public function description(): string
	{
		return 'Replaces one passage of an entry\'s Markdown body with new text, for a small change such as a typo or a sentence, without sending the whole body back. "find" must be copied exactly from the body get_entry answered and appear in it exactly once; send more of the text around it when it\'s found more than once. Needs the revision get_entry answered.';
	}

	#[Override]
	public function input(): array
	{
		return [
			'type'                 => 'object',
			'properties'           => [
				'id'       => ['type' => 'string'],
				'revision' => ['type' => 'string', 'description' => 'The revision get_entry answered.'],
				'find'     => ['type' => 'string', 'minLength' => 1, 'description' => 'The exact passage to replace.'],
				'replace'  => ['type' => 'string', 'description' => 'What to put in its place; "" takes it out.']
			],
			'required'             => ['id', 'revision', 'find', 'replace'],
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
		$entry = $this->entries->find($context, self::text($input, 'id'));

		try {
			$updated = $context->entries->replaceInBody($context->actor, $entry, self::text($input, 'find'), self::text($input, 'replace'), self::text($input, 'revision'));
		} catch (EditRefused $refused) {
			throw Fields::refused($refused);
		}

		$item = $this->entries->item($context, $updated);

		return new ToolResult(['id' => $item['id'] ?? null, 'title' => $updated->title, 'revision' => $item['revision'] ?? null, 'preview' => $item['preview'] ?? null], sprintf('Edited the body of “%s”.', $updated->title));
	}
}
