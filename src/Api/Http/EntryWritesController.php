<?php

/**
 * API entry writes controller.
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
use Blush\Api\ApiEntries;
use Blush\Api\ApiError;
use Blush\Api\ApiIncludes;
use Blush\Api\ApiQuery;
use Blush\Api\ApiResponses;
use Blush\Api\ApiWrites;
use Blush\Api\EntryResource;
use Blush\Api\MediaResource;
use Blush\Content\Editing\EditRefused;
use Blush\Content\Editing\EntryEdit;
use Blush\Content\Editing\NewEntry;
use Blush\Content\Entries;
use Blush\Content\Entry\Entry;
use Blush\Content\Status;
use Blush\Content\Type\ContentType;
use Blush\Content\Writer\WriteException;
use Blush\Field\Fields\ListField;
use Blush\Field\Fields\MediaField;
use Blush\Http\Status as HttpStatus;
use Blush\Media\MediaException;
use Blush\Support\Uuid;
use Blush\Tool\ToolContext;

/**
 * Answers the API's writes to entries (D-762), over the one write path
 * (`EntryEditor`), so it refuses what the admin and MCP refuse:
 *
 * - `POST /entries` makes a draft: `type` and `title`, and any of
 *   `subtitle`, `slug`, `published`, `image`, `parent`, `language`,
 *   `fields`, `relations`, and `body` (Markdown). `201`, with a
 *   `Location`.
 * - `PATCH /entries/{id}` changes only what it's sent (`null` removes):
 *   `title`, `subtitle`, `slug`, `published`, `status` (`draft`,
 *   `published`, or `scheduled` with a future `published`), `image`,
 *   `parent`, `fields`, `relations`, and `body`; `redirect: false` keeps
 *   a moved published entry's old address from redirecting. An entry in
 *   the trash takes `status` alone, which restores it.
 * - `DELETE /entries/{id}` moves it to the trash (D-484).
 *
 * Changing one needs its `revision` in `If-Match`. Values are written as
 * reads send them: `fields` only the type declares (D-743), media as
 * ids, `parent` as an id (`null` for the top), and `relations` by name,
 * as targets' ids or slugs. Each answers the entry, as `GET
 * /entries/{id}` does with `?body=`, with its new revision.
 */
final readonly class EntryWritesController
{
	/**
	 * What every write takes.
	 *
	 * @var list<string>
	 */
	private const array COMMON = ['title', 'subtitle', 'slug', 'published', 'image', 'parent', 'fields', 'relations', 'body'];

	public function __construct(
		private ApiWrites $writes,
		private ApiResponses $responses,
		private ApiEntries $entries,
		private EntryResource $resource,
		private MediaResource $media,
		private Entries $content
	) {}

	public function create(ServerRequestInterface $request): ResponseInterface
	{
		return $this->writes->run($request, function (ToolContext $context) use ($request): ResponseInterface {
			$body  = self::bodyChoice($request);
			$input = $this->writes->json($request);

			if (array_key_exists('status', $input)) {
				throw ApiError::invalidBody('New entries are drafts; leave "status" out, and publish with PATCH after.', 'status');
			}

			ApiWrites::only($input, ['type', 'language', ...self::COMMON]);

			$name = ApiWrites::text($input, 'type') ?? throw ApiError::invalidBody('Send the entry\'s "type".', 'type');
			$type = $this->entries->types()[$name] ?? throw ApiError::invalidBody(sprintf('There is no "%s" type.', $name), 'type');

			[$set] = $this->changes($type, $input);
			unset($set['title']);

			try {
				$entry = $context->entries->create($context->actor, new NewEntry(
					type: $type->name,
					title: ApiWrites::text($input, 'title') ?? throw ApiError::invalidBody('Send the entry\'s "title".', 'title'),
					slug: ApiWrites::text($input, 'slug'),
					set: $set,
					body: ApiWrites::text($input, 'body') ?? '',
					parent: $this->parent($type, $input),
					language: ApiWrites::text($input, 'language')
				));
			} catch (EditRefused $refused) {
				throw ApiError::refused($refused, $this->field($type, $refused->field));
			}

			return $this->responses->written($this->item($entry, $body), HttpStatus::Created, $this->responses->url('/entries/' . $entry->id));
		});
	}

	public function update(ServerRequestInterface $request, string $id): ResponseInterface
	{
		return $this->writes->run($request, function (ToolContext $context) use ($request, $id): ResponseInterface {
			$body     = self::bodyChoice($request);
			$entry    = $this->writes->entry($context, $id);
			$revision = $this->writes->revision($request);
			$input    = $this->writes->json($request);

			ApiWrites::only($input, ['status', 'redirect', ...self::COMMON]);

			if ($input === []) {
				throw ApiError::invalidBody('Send what to change.');
			}

			$status = ApiWrites::text($input, 'status');

			if ($entry->status === Status::Trash) {
				return $this->responses->written($this->item($this->restore($context, $entry, $input, $status, $revision), $body));
			}

			if ($status === 'trash') {
				throw ApiError::invalidBody('Move an entry to the trash with DELETE.', 'status');
			}

			if (array_key_exists('redirect', $input) && ! is_bool($input['redirect'])) {
				throw ApiError::invalidBody('"redirect" is true or false.', 'redirect');
			}

			[$set, $remove] = $this->changes($entry->type, $input);
			$published      = null;

			if ($status !== null && array_key_exists('published', $set)) {
				$published = is_string($set['published']) ? $set['published'] : null;
				unset($set['published']);
			}

			try {
				$updated = $context->entries->update($context->actor, $entry, new EntryEdit(
					set: $set,
					remove: $remove,
					body: ApiWrites::text($input, 'body'),
					status: $status,
					published: $published,
					slug: ApiWrites::text($input, 'slug'),
					parent: $this->parent($entry->type, $input, moving: true),
					redirect: ($input['redirect'] ?? true) === true
				), $revision);
			} catch (EditRefused $refused) {
				throw ApiError::refused($refused, $this->field($entry->type, $refused->field));
			}

			return $this->responses->written($this->item($updated, $body));
		});
	}

	public function delete(ServerRequestInterface $request, string $id): ResponseInterface
	{
		return $this->writes->run($request, function (ToolContext $context) use ($request, $id): ResponseInterface {
			$body     = self::bodyChoice($request);
			$entry    = $this->writes->entry($context, $id);
			$revision = $this->writes->revision($request);

			[$trashed] = $context->entries->trash($context->actor, $entry, $revision);

			return $this->responses->written($this->item($trashed, $body));
		});
	}

	/**
	 * Restores an entry from the trash, with the status a change asks
	 * for, and nothing else.
	 *
	 * @param  array<string, mixed> $input
	 * @throws ApiError
	 * @throws EditRefused
	 */
	private function restore(ToolContext $context, Entry $entry, array $input, ?string $status, string $revision): Entry
	{
		if (array_keys($input) !== ['status'] || ! in_array($status, ['draft', 'published'], true)) {
			throw ApiError::refused(EditRefused::unprocessable(sprintf('"%s" is in the trash; restore it first by sending "status" alone, as "draft" or "published".', $entry->title), 'status'));
		}

		return $context->entries->restore($context->actor, $entry, Status::from($status), $revision);
	}

	/**
	 * Returns the front matter a write sets and removes, from the keys
	 * reads send: the entry's own (`title`, `subtitle`, `published`,
	 * `image`), `fields`, and `relations`. A slug, a parent, and a body
	 * are the write's own.
	 *
	 * @param  array<string, mixed> $input
	 * @return array{array<string, mixed>, list<string>}
	 * @throws ApiError
	 * @throws MediaException
	 */
	private function changes(ContentType $type, array $input): array
	{
		$set    = [];
		$remove = [];

		foreach (['title', 'subtitle', 'published'] as $key) {
			if (! array_key_exists($key, $input)) {
				continue;
			}

			if ($input[$key] === null && $key !== 'title') {
				$remove[] = $key;
			} else {
				$set[$key] = ApiWrites::text($input, $key);
			}
		}

		if (array_key_exists('image', $input)) {
			if ($input['image'] === null) {
				$remove[] = 'image';
			} else {
				$set['image'] = $this->mediaReference($input['image'], 'image');
			}
		}

		foreach (self::object($input, 'fields') as $name => $value) {
			$field = $this->resource->declared($type)[$name] ?? throw ApiError::invalidBody(sprintf('%s have no "%s" field.', $type->labels->plural, $name), "fields.{$name}");

			if ($value === null) {
				$remove[] = $name;
				continue;
			}

			$set[$name] = match (true) {
				$field instanceof MediaField => $this->mediaReference($value, "fields.{$name}"),
				$field instanceof ListField && $field->item instanceof MediaField => array_map(
					fn (mixed $item): string => $this->mediaReference($item, "fields.{$name}"),
					is_array($value) && array_is_list($value) ? $value : throw ApiError::invalidBody(sprintf('"%s" is a list of media ids.', $name), "fields.{$name}")
				),
				default => $value
			};
		}

		foreach (self::object($input, 'relations') as $name => $value) {
			$relation = $this->resource->listed($type)[$name] ?? throw ApiError::invalidBody(sprintf('%s have no "%s" relation.', $type->labels->plural, $name), "relations.{$name}");
			$remove   = [...$remove, ...$relation->aliases];

			if ($value === null || $value === []) {
				$remove[] = $relation->field;
				continue;
			}

			$values = is_string($value) ? [$value] : $value;

			if (! is_array($values) || ! array_is_list($values)) {
				throw ApiError::invalidBody(sprintf('"%s" is a list of ids or slugs.', $name), "relations.{$name}");
			}

			$slugs = [];

			foreach ($values as $target) {
				$slugs[] = $this->target($relation->to, $target, "relations.{$name}");
			}

			$set[$relation->field] = $relation->multiple ? $slugs : (count($slugs) === 1 ? $slugs[0] : throw ApiError::invalidBody(sprintf('"%s" takes one target.', $name), "relations.{$name}"));
		}

		return [$set, array_values(array_unique($remove))];
	}

	/**
	 * Returns a relation's target as front matter names it: its slug, from
	 * an id or a slug.
	 *
	 * @param  list<string> $types The types the relation points at.
	 * @throws ApiError
	 */
	private function target(array $types, mixed $value, string $field): string
	{
		if (! is_string($value) || trim($value) === '') {
			throw ApiError::invalidBody('A relation\'s targets are ids or slugs.', $field);
		}

		if (! Uuid::isValid($value)) {
			return $value;
		}

		$entry = $this->content->find($value);

		return $entry !== null && in_array($entry->type->name, $types, true)
			? $entry->slug
			: throw ApiError::refused(EditRefused::unprocessable(sprintf('No entry it may point at has the id "%s".', $value)), $field);
	}

	/**
	 * Returns a media file's reference, as front matter names it, from
	 * its id.
	 *
	 * @throws ApiError
	 * @throws MediaException
	 */
	private function mediaReference(mixed $id, string $field): string
	{
		$record = is_string($id) && Uuid::isValid($id) ? $this->media->find($id) : null;

		return $record->url ?? throw ApiError::refused(EditRefused::unprocessable(sprintf('No media file has the id "%s".', is_scalar($id) ? (string) $id : '')), $field);
	}

	/**
	 * Returns the key of the page a tree's page goes under, from its id
	 * (`''` for the top, when moving), or `null` when it isn't asked.
	 *
	 * @param  array<string, mixed> $input
	 * @throws ApiError
	 */
	private function parent(ContentType $type, array $input, bool $moving = false): ?string
	{
		if (! array_key_exists('parent', $input)) {
			return null;
		}

		$id = $input['parent'];

		if ($id === null) {
			return $moving ? '' : null;
		}

		$parent = is_string($id) && Uuid::isValid($id) ? $this->content->find($id) : null;

		return $parent !== null && $parent->type->name === $type->name
			? $parent->key
			: throw ApiError::refused(EditRefused::unprocessable(sprintf('No %s has the id "%s".', $type->labels->singular, is_scalar($id) ? (string) $id : '')), 'parent');
	}

	/**
	 * Returns a key's value as an object, by key, or none when it's left
	 * out.
	 *
	 * @param  array<string, mixed> $input
	 * @return array<string, mixed>
	 * @throws ApiError
	 */
	private static function object(array $input, string $key): array
	{
		$value = $input[$key] ?? [];

		if (! is_array($value) || ($value !== [] && array_is_list($value))) {
			throw ApiError::invalidBody(sprintf('"%s" is an object, by name.', $key), $key);
		}

		$values = [];

		foreach ($value as $name => $item) {
			$values[(string) $name] = $item;
		}

		return $values;
	}

	/**
	 * Returns the field a refusal is about as a write names it: a declared
	 * field under `fields.`, a relation's under `relations.`.
	 */
	private function field(ContentType $type, ?string $field): ?string
	{
		if ($field === null) {
			return null;
		}

		foreach ($this->resource->listed($type) as $name => $relation) {
			if (in_array($field, $relation->keys(), true)) {
				return "relations.{$name}";
			}
		}

		return isset($this->resource->declared($type)[$field]) ? "fields.{$field}" : $field;
	}

	/**
	 * Returns what `?body=` asks a write's answer to hold.
	 *
	 * @throws ApiError
	 */
	private static function bodyChoice(ServerRequestInterface $request): string
	{
		return ApiQuery::from($request, ['body'])->choice('body', EntryResource::BODIES, 'html');
	}

	/**
	 * Returns an entry as a write answers it, with its new revision.
	 *
	 * @return array<string, mixed>
	 * @throws MediaException
	 */
	private function item(Entry $entry, string $body): array
	{
		$item = $this->resource->item($entry, $body, new ApiIncludes());

		try {
			$item['revision'] = $this->content->editable($entry)->version;
		} catch (WriteException) {
		}

		return $item;
	}
}
