<?php

/**
 * API media writes controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Api\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Blush\Api\ApiError;
use Blush\Api\ApiQuery;
use Blush\Api\ApiResponses;
use Blush\Api\ApiWrites;
use Blush\Api\MediaResource;
use Blush\Content\Editing\EditRefused;
use Blush\Http\Status;
use Blush\Media\Index\MediaRecord;
use Blush\Media\MediaException;
use Blush\Support\Uuid;
use Blush\Tool\ToolContext;

/**
 * Answers the API's writes to media (D-762), over the one write path
 * (`MediaEditor`), so uploads follow the upload rules by kind (D-406, no
 * SVG, D-497) and changes the media capabilities (D-407):
 *
 * - `POST /media` adds a file, sent as a form sends one
 *   (`multipart/form-data`, the file as `file`), with `alt`, `caption`,
 *   and `title` beside it. `201`, with a `Location`; details that
 *   couldn't be saved are listed in `warnings`, since the file was.
 * - `PATCH /media/{id}` changes `alt`, `caption`, and `title` (`null`
 *   or `""` removes one).
 *
 * Each answers the file as `GET /media/{id}` does. No deleting.
 */
final readonly class MediaWritesController
{
	/**
	 * The details a write changes.
	 *
	 * @var list<string>
	 */
	private const array DETAILS = ['alt', 'caption', 'title'];

	public function __construct(
		private ApiWrites $writes,
		private ApiResponses $responses,
		private MediaResource $resource
	) {}

	public function upload(ServerRequestInterface $request): ResponseInterface
	{
		return $this->writes->run($request, function (ToolContext $context) use ($request): ResponseInterface {
			ApiQuery::from($request, []);

			$upload = $request->getUploadedFiles()['file'] ?? null;
			$form   = $request->getParsedBody();
			$form   = is_array($form) ? $form : [];

			if (! $upload instanceof UploadedFileInterface) {
				// PHP drops the whole body when it's over `post_max_size`.
				throw ApiError::invalidBody('Send the file as "file" in a multipart/form-data body. It may also be larger than the site allows.', 'file');
			}

			if ($upload->getError() !== UPLOAD_ERR_OK) {
				throw match ($upload->getError()) {
					UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => new ApiError('too_large', 'The file is larger than the site allows.', Status::ContentTooLarge, 'file'),
					UPLOAD_ERR_PARTIAL, UPLOAD_ERR_NO_FILE    => ApiError::invalidBody('The file didn\'t arrive whole. Try again.', 'file'),
					default                                   => new ApiError('server_error', 'The server couldn\'t take the file.', Status::InternalServerError)
				};
			}

			$set = [];

			foreach ($form as $key => $value) {
				if (! in_array($key, self::DETAILS, true)) {
					throw ApiError::invalidBody(sprintf('"%s" can\'t be sent with a file; it takes: file, %s.', $key, implode(', ', self::DETAILS)), (string) $key);
				}

				if (is_string($value) && trim($value) !== '') {
					$set[$key] = $value;
				}
			}

			$uploaded = $context->media->upload($context->actor, (string) $upload->getClientFilename(), $upload->getSize(), $upload->moveTo(...));
			$warnings = [];

			if ($set !== []) {
				try {
					$context->media->update($context->actor, $uploaded->relative, $set);
				} catch (EditRefused $refused) {
					$warnings[] = "Its details weren't saved: {$refused->getMessage()}";
				}
			}

			$record = $uploaded->id === null ? null : $this->resource->find($uploaded->id);
			$item   = $record === null ? ['id' => $uploaded->id, 'url' => $uploaded->file->url] : $this->resource->item($record);

			return $this->responses->written(
				[...$item, ...($warnings === [] ? [] : ['warnings' => $warnings])],
				Status::Created,
				$uploaded->id === null ? null : $this->responses->url('/media/' . $uploaded->id)
			);
		});
	}

	public function update(ServerRequestInterface $request, string $id): ResponseInterface
	{
		return $this->writes->run($request, function (ToolContext $context) use ($request, $id): ResponseInterface {
			ApiQuery::from($request, []);

			$record = Uuid::isValid($id) ? $this->resource->find($id) : null;

			if ($record === null) {
				throw ApiError::notFound(sprintf('No media file has the id "%s".', $id));
			}

			$input = $this->writes->json($request);

			ApiWrites::only($input, self::DETAILS);

			$set    = [];
			$remove = [];

			foreach ($input as $key => $value) {
				if ($value === null || (is_string($value) && trim($value) === '')) {
					$remove[] = $key;
				} else {
					$set[$key] = ApiWrites::text($input, $key);
				}
			}

			if ($set === [] && $remove === []) {
				throw ApiError::invalidBody(sprintf('Send what to change: %s.', implode(', ', self::DETAILS)));
			}

			$context->media->update($context->actor, $record->key, $set, $remove);

			return $this->responses->written($this->resource->item($this->refound($id, $record)));
		});
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
}
