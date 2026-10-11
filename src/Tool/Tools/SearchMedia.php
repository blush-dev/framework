<?php

/**
 * Search media tool.
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
use Blush\Media\Index\MediaLibrary;
use Blush\Media\Index\MediaQuery;
use Blush\Media\Index\MediaRecord;
use Blush\Media\MediaException;
use Blush\Media\MediaKind;
use Blush\Tool\ToolContext;
use Blush\Tool\Tool;
use Blush\Tool\ToolError;
use Blush\Tool\ToolResult;

/**
 * `search_media` (D-755): files in the media library by kind, by words in
 * their title, alt text, or caption, and those missing alt text, which
 * is public, as the API's is (D-736).
 */
final class SearchMedia extends Tool
{
	public function __construct(
		private readonly MediaLibrary $library,
		private readonly MediaResource $resource
	) {}

	#[Override]
	public function title(): string
	{
		return 'Search Media';
	}

	#[Override]
	public function description(): string
	{
		return 'Finds files in the media library (images, video, audio, documents) by kind, by words in their name, title, alt text, or caption, and those missing alt text. Answers each file\'s id, address, size, title, alt text, and caption; get_media adds where it\'s used.';
	}

	#[Override]
	public function input(): array
	{
		return [
			'type'                 => 'object',
			'properties'           => [
				'search'      => ['type' => 'string', 'maxLength' => 200],
				'kind'        => ['type' => 'string', 'enum' => array_column(MediaKind::cases(), 'value')],
				'missing_alt' => ['type' => 'boolean', 'description' => 'Only images with no alt text.'],
				'limit'       => ['type' => 'integer', 'minimum' => 1, 'maximum' => SearchEntries::MOST],
				'cursor'      => ['type' => 'string']
			],
			'additionalProperties' => false
		];
	}

	#[Override]
	public function handle(array $input, ToolContext $context): ToolResult
	{
		$limit  = is_int($input['limit'] ?? null) ? $input['limit'] : SearchEntries::LIMIT;
		$cursor = $input['cursor'] ?? null;
		$page   = $cursor === null ? 1 : (is_string($cursor) && ctype_digit($cursor) && (int) $cursor >= 1 ? (int) $cursor : throw new ToolError('"cursor" must be the next_cursor a search answered.'));

		try {
			$found = array_values(array_filter(
				$this->library->query(new MediaQuery(
					search: is_string($input['search'] ?? null) ? $input['search'] : '',
					kind: is_string($input['kind'] ?? null) ? MediaKind::from($input['kind']) : null,
					missingAlt: ($input['missing_alt'] ?? false) === true,
					per: PHP_INT_MAX
				))->records,
				static fn (MediaRecord $record): bool => $record->id() !== null
			));

			$items = array_map($this->resource->item(...), array_slice($found, ($page - 1) * $limit, $limit));
		} catch (MediaException $error) {
			throw new ToolError(sprintf('The media library can\'t be read: %s', $error->getMessage()));
		}

		return new ToolResult([
			'media'       => $items,
			'total'       => count($found),
			'next_cursor' => $page * $limit < count($found) ? (string) ($page + 1) : null
		], sprintf('Found %d file%s.', count($found), count($found) === 1 ? '' : 's'));
	}
}
