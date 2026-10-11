<?php

/**
 * Upload media tool.
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
use Blush\Content\Editing\EditRefused;
use Blush\Media\MediaException;
use Blush\Media\MediaKind;
use Blush\Mcp\McpContext;
use Blush\Mcp\Tool;
use Blush\Mcp\ToolAnnotations;
use Blush\Mcp\ToolError;
use Blush\Mcp\ToolResult;

/**
 * `upload_media` (D-756): a file sent as base64, with its name, alt text,
 * and caption, under the site's upload rules by kind (D-406) and never
 * SVG (D-497). Uploading from an address waits for the HTTP client
 * (D-620).
 */
final class UploadMedia extends Tool
{
	/**
	 * The most a file sent may be, in bytes, before it's decoded, so a
	 * call can't take the server's memory.
	 */
	public const int MOST = 20 * 1024 * 1024;

	public function __construct(private readonly MediaResource $resource)
	{}

	#[Override]
	public function title(): string
	{
		return 'Upload a Media File';
	}

	#[Override]
	public function description(): string
	{
		return 'Adds a file to the media library from its contents, as base64, with its file name (whose extension says its type), and alt text and a caption. Only the types the site takes, up to the sizes it allows; never SVG. Answers the file with its id and address, for a body\'s Markdown image or a field.';
	}

	#[Override]
	public function input(): array
	{
		return [
			'type'                 => 'object',
			'properties'           => [
				'name'    => ['type' => 'string', 'minLength' => 1, 'maxLength' => 200, 'description' => 'The file name, with its extension, such as crust.jpg.'],
				'data'    => ['type' => 'string', 'minLength' => 1, 'maxLength' => (int) ceil(self::MOST / 3) * 4, 'description' => 'The file\'s contents, as base64.'],
				'alt'     => ['type' => 'string', 'maxLength' => 500],
				'caption' => ['type' => 'string', 'maxLength' => 1000]
			],
			'required'             => ['name', 'data'],
			'additionalProperties' => false
		];
	}

	#[Override]
	public function capability(): string
	{
		return MediaKind::UPLOAD_EVERY;
	}

	/**
	 * Any kind the account may upload will do; the kind of the file is
	 * checked when it's sent.
	 */
	#[Override]
	public function allows(McpContext $context): bool
	{
		return array_any(MediaKind::cases(), static fn (MediaKind $kind): bool => $context->permissions->mayUpload($context->account(), $kind));
	}

	#[Override]
	public function annotations(): ToolAnnotations
	{
		return ToolAnnotations::writes(draftsSafe: true);
	}

	#[Override]
	public function handle(array $input, McpContext $context): ToolResult
	{
		$bytes = base64_decode(self::text($input, 'data'), true);

		if ($bytes === false || $bytes === '') {
			throw new ToolError('"data" must be the file\'s contents as base64.');
		}

		try {
			$uploaded = $context->media->uploadBytes($context->actor, self::text($input, 'name'), $bytes);
		} catch (EditRefused $refused) {
			throw Fields::refused($refused);
		}

		$details = array_filter(['alt' => $input['alt'] ?? null, 'caption' => $input['caption'] ?? null], static fn (mixed $value): bool => is_string($value) && trim($value) !== '');
		$note    = '';

		try {
			if ($details !== []) {
				$context->media->update($context->actor, $uploaded->relative, $details);
			}
		} catch (EditRefused $refused) {
			$note = " Its alt text and caption weren't saved: {$refused->getMessage()}";
		}

		try {
			$record = $uploaded->id === null ? null : $this->resource->find($uploaded->id);
			$item   = $record === null ? ['id' => $uploaded->id, 'url' => $uploaded->file->url] : $this->resource->item($record);
		} catch (MediaException) {
			$item = ['id' => $uploaded->id, 'url' => $uploaded->file->url];
		}

		return new ToolResult($item, sprintf('Uploaded %s.%s', basename($uploaded->relative), $note));
	}
}
