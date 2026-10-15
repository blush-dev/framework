<?php

/**
 * Create entry tool.
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
use Blush\Content\Editing\NewEntry;
use Blush\Tool\ToolContext;
use Blush\Tool\ToolEntries;
use Blush\Tool\Tool;
use Blush\Tool\ToolAnnotations;
use Blush\Tool\ToolError;
use Blush\Tool\ToolResult;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * `create_entry` (D-756): a new entry, at its workflow's `initial` (a
 * draft in `standard`, D-851), through the one
 * write path, answering it with its revision and links to its editor and
 * a preview, so the AI can hand the person something to review.
 */
final class CreateEntry extends Tool
{
	public function __construct(
		private readonly ToolEntries $entries,
		private readonly Messages $messages
	) {}

	#[Override]
	public function title(): Text
	{
		return $this->messages->t('tool.create_entry.title', 'Create a Draft');
	}

	#[Override]
	public function description(): Text
	{
		return $this->messages->t('tool.create_entry.description', 'Creates a new entry, a draft unless its type\'s workflow starts elsewhere: a type, a title, its fields (by the names get_type gives; relationships such as tags take the related entries\' slugs), and a Markdown body. It\'s never published; set_status does that, when asked. Answers the entry, with its revision and links to review it in the site\'s editor and to preview it.');
	}

	#[Override]
	public function input(): array
	{
		return [
			'type'                 => 'object',
			'properties'           => [
				'type'     => ['type' => 'string', 'description' => 'The type\'s name, as list_types gives it.'],
				'title'    => ['type' => 'string', 'minLength' => 1, 'maxLength' => 300],
				'slug'     => ['type' => 'string', 'description' => 'The last part of its address: lowercase letters, numbers, and hyphens. Made from the title when left out.'],
				'fields'   => ['type' => 'object', 'description' => 'Field values by name, as get_type lists them. A media field, such as image, takes a media file\'s id.'],
				'body'     => ['type' => 'string', 'description' => 'The body, in Markdown.'],
				'parent'   => ['type' => 'string', 'description' => 'For a page of a tree, the slug path of the page to put it under, such as "about".'],
				'language' => ['type' => 'string', 'description' => 'The language\'s code, when the site has several.']
			],
			'required'             => ['type', 'title'],
			'additionalProperties' => false
		];
	}

	#[Override]
	public function capability(): ContentAction
	{
		return ContentAction::Create;
	}

	#[Override]
	public function annotations(): ToolAnnotations
	{
		return ToolAnnotations::writes(draftsSafe: true);
	}

	#[Override]
	public function handle(array $input, ToolContext $context): ToolResult
	{
		$fields = Fields::from($input['fields'] ?? []);

		if (array_key_exists('status', $fields)) {
			throw new ToolError((string) $this->messages->t('tool.create_entry.no_status', 'New entries are drafts; leave "status" out, and use set_status after, when asked to publish.'));
		}

		try {
			$entry = $context->entries->create($context->actor, new NewEntry(
				type: self::text($input, 'type'),
				title: self::text($input, 'title'),
				slug: self::optional($input, 'slug'),
				set: $fields,
				body: is_string($input['body'] ?? null) ? $input['body'] : '',
				parent: self::optional($input, 'parent'),
				language: self::optional($input, 'language')
			));
		} catch (EditRefused $refused) {
			throw Fields::refused($refused, $this->messages);
		}

		return new ToolResult($this->entries->item($context, $entry), $this->messages->t('tool.create_entry.created', 'Created the draft “{title}”.', title: $entry->title));
	}
}
