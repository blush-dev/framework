<?php

/**
 * Feed controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Feed;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Content\ContentRepository;
use Blush\Content\Query\InvalidQuery;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\Profiles;
use Blush\Http\NotFound;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Markdown\MarkdownException;
use Blush\Theme\ThemeException;
use Blush\View\DocumentRenderer;
use Blush\View\ViewException;

/**
 * Serves a feed: a type's collection feed (the homepage's at `/feed`),
 * with `{name}`, a term's or a profile's (D-351), or with
 * `{field}` and `{profile}`, a person's entries of the type under that
 * people field. The theme renders it with
 * `feed-{format}-{type}` → `feed-{format}` (D-029). An empty feed is
 * still a feed.
 */
final readonly class FeedController
{
	public function __construct(
		private FeedBuilder $builder,
		private ContentRepository $content,
		private ContentTypes $types,
		private FeedConfig $config,
		private DocumentRenderer $documents
	) {}

	/**
	 * @throws NotFound
	 * @throws InvalidQuery
	 * @throws MarkdownException
	 * @throws ThemeException
	 * @throws ViewException
	 */
	public function __invoke(ServerRequestInterface $request, string $type, string $format, ?string $name = null, ?string $field = null, ?string $profile = null): ResponseInterface
	{
		$feedFormat  = FeedFormat::tryFrom($format);
		$contentType = $this->types->find($type);

		if ($feedFormat === null || ! $this->config->has($feedFormat) || $contentType === null || ! $contentType->hasFeed()) {
			throw new NotFound(sprintf('There is no %s feed for "%s".', $format, $type));
		}

		$profiles = $this->types->profiles();

		if ($field !== null && $profile !== null) {
			$people = $contentType->archivedPeople()[$field] ?? null;
			$entry  = $profiles === null || $people === null ? null : $this->content->term($profiles->name, $profile);

			if ($people === null || $entry === null || ! $entry->isPublished() || ! $entry->isRoutable()) {
				throw new NotFound(sprintf('There is no "%s" %s "%s".', $type, $field, $profile));
			}

			$feed = $this->builder->person($contentType, $people, $entry, $feedFormat);
		} elseif ($contentType instanceof Profiles) {
			$entry = $name === null ? null : $this->content->term($contentType->name, $name);

			if ($entry === null || ! $entry->isPublished() || ! $entry->isRoutable()) {
				throw new NotFound(sprintf('There is no profile "%s".', $name ?? ''));
			}

			$feed = $this->builder->profile($contentType, $entry, $feedFormat);
		} elseif ($name === null) {
			$feed = $this->builder->collection($contentType, $feedFormat);
		} else {
			// A hierarchical term's `{name}` is its path; the term is its last slug.
			$term = $this->types->hasTermPages($contentType->name) ? $this->content->term($contentType->name, basename($name)) : null;

			if ($term === null || ! $term->isPublished() || ! $term->isRoutable()) {
				throw new NotFound(sprintf('There is no "%s" term "%s".', $type, $name));
			}

			$feed = $this->builder->term($contentType, $term, $feedFormat);
		}

		$body = $this->documents->render($request, ["feed-{$format}-{$type}", "feed-{$format}"], ['feed' => $feed]);

		return new Response(Status::Ok, ['Content-Type' => $feedFormat->mediaType() . '; charset=UTF-8'], $body);
	}
}
