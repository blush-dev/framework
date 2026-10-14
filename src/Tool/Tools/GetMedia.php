<?php

/**
 * Get media tool.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tool\Tools;

use Override;
use Blush\Api\MediaResource;
use Blush\Content\Entries;
use Blush\Media\MediaException;
use Blush\Media\MediaUsage;
use Blush\Tool\ToolContext;
use Blush\Tool\ToolEntries;
use Blush\Tool\Tool;
use Blush\Tool\ToolError;
use Blush\Tool\ToolResult;
use Blush\Support\Uuid;
use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * `get_media` (D-755): one file in full, by id: its alt text, caption,
 * sizes, and the metadata of its kind (D-287 to D-291), with the entries
 * the connection sees that use it.
 */
final class GetMedia extends Tool
{
	public function __construct(
		private readonly MediaResource $resource,
		private readonly MediaUsage $usage,
		private readonly Entries $content,
		private readonly ToolEntries $entries,
		private readonly Messages $messages
	) {}

	#[Override]
	public function title(): Text
	{
		return $this->messages->t('tool.get_media.title', 'Get a Media File');
	}

	#[Override]
	public function description(): Text
	{
		return $this->messages->t('tool.get_media.description', 'Gets one file in the media library by id: its address, dimensions, alt text, caption, sizes, and other details, and the entries that use it.');
	}

	#[Override]
	public function input(): array
	{
		return [
			'type'                 => 'object',
			'properties'           => ['id' => ['type' => 'string', 'description' => 'The file\'s id, as search_media gives it.']],
			'required'             => ['id'],
			'additionalProperties' => false
		];
	}

	#[Override]
	public function handle(array $input, ToolContext $context): ToolResult
	{
		$id = self::text($input, 'id');

		try {
			$record = Uuid::isValid($id) ? $this->resource->find($id) : null;

			if ($record === null) {
				throw new ToolError((string) $this->messages->t('tool.get_media.not_found', 'No media file has the id "{id}".', id: $id));
			}

			$item = $this->resource->item($record);
		} catch (MediaException $error) {
			throw new ToolError((string) $this->messages->t('tool.get_media.unreadable', 'The media library can\'t be read: {problem}', problem: $error->getMessage()));
		}

		$used = [];

		foreach ($this->usage->entries($record->key, id: $record->id()) as $found) {
			$entry = $found['id'] === null ? null : $this->content->find($found['id']);

			if ($entry !== null && $this->entries->sees($context, $entry)) {
				$used[] = $this->entries->stub($entry);
			}
		}

		return new ToolResult([...$item, 'used_in' => $used]);
	}
}
