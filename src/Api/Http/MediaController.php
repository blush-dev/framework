<?php

/**
 * API media controller.
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
use Blush\Api\ApiError;
use Blush\Api\ApiQuery;
use Blush\Api\ApiResponses;
use Blush\Api\MediaResource;
use Blush\Media\Index\MediaLibrary;
use Blush\Media\Index\MediaQuery;
use Blush\Media\Index\MediaRecord;
use Blush\Media\MediaKind;
use Blush\Support\Uuid;

/**
 * Answers `/media` and `/media/{id}` (D-736): every file in the library
 * with an id, since media is public. A list is newest upload first,
 * filtered by `kind` (`image`, `video`, `audio`, `document`, or
 * `file`) and `search` (its path and metadata, as the library searches),
 * and paged.
 */
final readonly class MediaController
{
	public function __construct(
		private ApiResponses $responses,
		private MediaResource $resource,
		private MediaLibrary $library
	) {}

	public function index(ServerRequestInterface $request): ResponseInterface
	{
		return $this->responses->answer($request, function () use ($request): array {
			$query   = ApiQuery::from($request, ['kind', 'search', 'page', 'per_page']);
			$kind    = $query->has('kind') ? MediaKind::from($query->choice('kind', array_column(MediaKind::cases(), 'value'), '')) : null;
			$page    = $query->page();
			$perPage = $query->perPage();

			$found = array_values(array_filter(
				$this->library->query(new MediaQuery(search: $query->string('search') ?? '', kind: $kind, per: PHP_INT_MAX))->records,
				static fn (MediaRecord $record): bool => $record->id() !== null
			));

			return $this->responses->page(
				$request,
				array_map($this->resource->item(...), array_slice($found, ($page - 1) * $perPage, $perPage)),
				count($found),
				$page,
				$perPage
			);
		});
	}

	public function show(ServerRequestInterface $request, string $id): ResponseInterface
	{
		return $this->responses->answer($request, function () use ($request, $id): array {
			ApiQuery::from($request, []);

			$record = Uuid::isValid($id) ? $this->resource->find($id) : null;

			return $record === null
				? throw ApiError::notFound(sprintf('No media file has the id "%s".', $id))
				: $this->resource->item($record);
		});
	}
}
