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

namespace Blush\Mcp\Tools;

use Override;
use Blush\Api\MediaResource;
use Blush\Content\Entries;
use Blush\Media\MediaException;
use Blush\Media\MediaUsage;
use Blush\Mcp\McpContext;
use Blush\Mcp\McpEntries;
use Blush\Mcp\Tool;
use Blush\Mcp\ToolError;
use Blush\Mcp\ToolResult;
use Blush\Support\Uuid;

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
		private readonly McpEntries $entries
	) {}

	#[Override]
	public function title(): string
	{
		return 'Get a Media File';
	}

	#[Override]
	public function description(): string
	{
		return 'Gets one file in the media library by id: its address, dimensions, alt text, caption, sizes, and other details, and the entries that use it.';
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
	public function handle(array $input, McpContext $context): ToolResult
	{
		$id = self::text($input, 'id');

		try {
			$record = Uuid::isValid($id) ? $this->resource->find($id) : null;

			if ($record === null) {
				throw new ToolError(sprintf('No media file has the id "%s".', $id));
			}

			$item = $this->resource->item($record);
		} catch (MediaException $error) {
			throw new ToolError(sprintf('The media library can\'t be read: %s', $error->getMessage()));
		}

		$used = [];

		foreach ($this->usage->entries($record->key) as $found) {
			$entry = $found['id'] === null ? null : $this->content->find($found['id']);

			if ($entry !== null && $this->entries->sees($context, $entry)) {
				$used[] = $this->entries->stub($entry);
			}
		}

		return new ToolResult([...$item, 'used_in' => $used]);
	}
}
