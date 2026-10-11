<?php

/**
 * Update media tool.
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
use Blush\Auth\Capability;
use Blush\Content\Editing\EditRefused;
use Blush\Media\Index\MediaRecord;
use Blush\Media\MediaException;
use Blush\Tool\ToolContext;
use Blush\Tool\Tool;
use Blush\Tool\ToolAnnotations;
use Blush\Tool\ToolError;
use Blush\Tool\ToolResult;
use Blush\Support\Uuid;

/**
 * `update_media` (D-756): a file's alt text, caption, or title, by whose
 * file it is (D-407), as "fill in the missing alt text" asks.
 */
final class UpdateMedia extends Tool
{
	public function __construct(private readonly MediaResource $resource)
	{}

	#[Override]
	public function title(): string
	{
		return 'Update a Media File';
	}

	#[Override]
	public function description(): string
	{
		return 'Changes a media file\'s alt text, caption, or title, by its id. Alt text describes an image for someone who can\'t see it; keep it to a sentence. An empty value clears one.';
	}

	#[Override]
	public function input(): array
	{
		return [
			'type'                 => 'object',
			'properties'           => [
				'id'      => ['type' => 'string'],
				'alt'     => ['type' => 'string', 'maxLength' => 500],
				'caption' => ['type' => 'string', 'maxLength' => 1000],
				'title'   => ['type' => 'string', 'maxLength' => 300]
			],
			'required'             => ['id'],
			'additionalProperties' => false
		];
	}

	#[Override]
	public function capability(): Capability
	{
		return Capability::MediaEdit;
	}

	#[Override]
	public function annotations(): ToolAnnotations
	{
		return ToolAnnotations::writes(idempotent: true, draftsSafe: true);
	}

	/**
	 * Returns a file as it's described now, after a change.
	 *
	 * @throws MediaException
	 */
	private function refound(string $id, MediaRecord $record): MediaRecord
	{
		return $this->resource->find($id) ?? $record;
	}

	#[Override]
	public function handle(array $input, ToolContext $context): ToolResult
	{
		$id = self::text($input, 'id');

		try {
			$record = Uuid::isValid($id) ? $this->resource->find($id) : null;
		} catch (MediaException $error) {
			throw new ToolError(sprintf('The media library can\'t be read: %s', $error->getMessage()));
		}

		if ($record === null) {
			throw new ToolError(sprintf('No media file has the id "%s".', $id));
		}

		$set    = [];
		$remove = [];

		foreach (['alt', 'caption', 'title'] as $key) {
			if (! is_string($input[$key] ?? null)) {
				continue;
			}

			if (trim($input[$key]) === '') {
				$remove[] = $key;
			} else {
				$set[$key] = $input[$key];
			}
		}

		if ($set === [] && $remove === []) {
			throw new ToolError('Send "alt", "caption", or "title" to change.');
		}

		try {
			$context->media->update($context->actor, $record->key, $set, $remove);
			return new ToolResult($this->resource->item($this->refound($id, $record)), sprintf('Updated %s.', basename($record->key)));
		} catch (EditRefused $refused) {
			throw Fields::refused($refused);
		} catch (MediaException $error) {
			throw new ToolError(sprintf('The media library can\'t be read: %s', $error->getMessage()));
		}
	}
}
