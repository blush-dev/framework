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

namespace Blush\Tool\Tools;

use Override;
use Blush\Auth\ContentAction;
use Blush\Content\Editing\EditRefused;
use Blush\Content\Entry\Entry;
use Blush\Content\Relation\Referrers;
use Blush\Tool\ToolContext;
use Blush\Tool\ToolEntries;
use Blush\Tool\Tool;
use Blush\Tool\ToolAnnotations;
use Blush\Tool\ToolError;
use Blush\Tool\ToolResult;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * `trash_entry` (D-756): moves an entry to the trash, a status (D-484),
 * so it can be restored. Live entries that link to it are named first
 * (D-608), and it's trashed only once that's confirmed. Nothing is ever
 * deleted for good through MCP.
 */
final class TrashEntry extends Tool
{
	public function __construct(
		private readonly ToolEntries $entries,
		private readonly Referrers $referrers,
		private readonly Messages $messages
	) {}

	#[Override]
	public function title(): Text
	{
		return $this->messages->t('tool.trash_entry.title', 'Move an Entry to the Trash');
	}

	#[Override]
	public function description(): Text
	{
		return $this->messages->t('tool.trash_entry.description', 'Moves an entry to the trash, off the site; restore_entry brings it back. When live entries link to it, it names them and does nothing until it\'s sent again with confirm: true. Needs the revision get_entry answered.');
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
	public function handle(array $input, ToolContext $context): ToolResult
	{
		$entry = $this->entries->find($context, self::text($input, 'id'));

		if (($input['confirm'] ?? false) !== true) {
			$live = array_values(array_filter(
				array_column($this->referrers->sources($entry), 'entry'),
				static fn (Entry $source): bool => $source->isPublished()
			));

			if ($live !== []) {
				throw new ToolError((string) $this->messages->t(
					'tool.trash_entry.linked',
					'{count, plural, one {# live entry links} other {# live entries link}} to “{title}”: {entries}{more, select, yes {, and more} other {}}. Trashing it takes it off the site, and those links stop working. Send confirm: true to trash it anyway.',
					count: count($live),
					title: $entry->title,
					entries: implode(', ', array_map(static fn (Entry $source): string => "“{$source->title}”", array_slice($live, 0, 8))),
					more: count($live) > 8 ? 'yes' : 'no'
				));
			}
		}

		try {
			[$trashed] = $context->entries->trash($context->actor, $entry, self::text($input, 'revision'));
		} catch (EditRefused $refused) {
			throw Fields::refused($refused, $this->messages);
		}

		return new ToolResult($this->entries->stub($trashed), $this->messages->t('tool.trash_entry.trashed', 'Moved “{title}” to the trash; restore_entry brings it back.', title: $entry->title));
	}
}
