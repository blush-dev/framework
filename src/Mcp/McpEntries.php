<?php

/**
 * MCP entries.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Mcp;

use Blush\Admin\AdminLinks;
use Blush\Api\ApiEntries;
use Blush\Api\ApiIncludes;
use Blush\Api\EntryResource;
use Blush\Auth\ContentAction;
use Blush\Content\Entries;
use Blush\Content\Entry\Entry;
use Blush\Content\Query\Query;
use Blush\Content\Status;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Visibility;
use Blush\Content\Writer\DocumentEditor;
use Blush\Content\Writer\WriteException;
use Blush\Media\MediaException;
use Blush\Preview\PreviewLinks;
use Blush\Support\Uuid;
use LogicException;

/**
 * What the MCP server's tools see of entries (D-755): every status the
 * account may see but the trash, unlike the API's public answer, since
 * MCP is never anonymous or cached and a writer's first questions are
 * about drafts. What anyone may read (published entries of the public
 * types, listed or unlisted) and what the account may edit; types that
 * aren't public only when the account may edit their entries (D-745).
 * Entries answer in the API's shapes (D-751).
 */
final readonly class McpEntries
{
	/**
	 * The statuses found unless others are asked for: all but the trash.
	 *
	 * @var list<Status>
	 */
	public const array DEFAULT_STATUSES = [Status::Published, Status::Draft, Status::Scheduled];

	public function __construct(
		private Entries $content,
		private ContentTypes $types,
		private ApiEntries $api,
		private EntryResource $resource,
		private AdminLinks $admin,
		private PreviewLinks $previews
	) {}

	/**
	 * Returns the types a context sees, by name: the public ones, and the
	 * others whose entries it may edit.
	 *
	 * @return array<string, ContentType>
	 */
	public function types(McpContext $context): array
	{
		$types = [];

		foreach ($this->types->all() as $type) {
			if ($type->public || $context->can(ContentAction::Edit, $type->name)) {
				$types[$type->name] = $type;
			}
		}

		return $types;
	}

	/**
	 * Returns a type a context sees, by name.
	 *
	 * @throws ToolError When it sees none by that name.
	 */
	public function type(McpContext $context, string $name): ContentType
	{
		return $this->types($context)[$name] ?? throw new ToolError(sprintf('There\'s no "%s" type; list_types says which there are.', $name));
	}

	/**
	 * Returns a query for the entries of some statuses a context sees,
	 * without listings' landing pages.
	 *
	 * @param list<Status> $statuses
	 */
	public function query(McpContext $context, array $statuses = self::DEFAULT_STATUSES): Query
	{
		$types  = array_keys($this->types($context));
		$public = array_keys(array_filter($this->types($context), static fn (ContentType $type): bool => $type->public));
		$query  = $this->content->query()
			->status(...$statuses)
			->visibility(...Visibility::cases())
			->withLanding(false)
			->type(...$types);

		$own = $context->permissions->alternatives($context->account(), ContentAction::Edit, $types);

		if ($own === null) {
			return $query;
		}

		$anyone = $public === [] ? [] : [static fn (Query $condition): Query => $condition->type(...$public)->status(Status::Published)->visibility(Visibility::Public, Visibility::Unlisted)];

		return $query->either(...$anyone, ...$own);
	}

	/**
	 * Returns an entry a context sees, by id or by its address on the
	 * site.
	 *
	 * @throws ToolError When it sees none there.
	 */
	public function find(McpContext $context, ?string $id, ?string $url = null): Entry
	{
		$entry = match (true) {
			$id !== null && Uuid::isValid($id) => $this->content->find($id),
			$url !== null                      => $this->api->at($url),
			default                            => null
		};

		if ($entry === null || ! $this->sees($context, $entry)) {
			throw new ToolError($id !== null
				? sprintf('There\'s no entry with the id "%s" that this connection may see.', $id)
				: sprintf('There\'s no published entry at "%s"; search_entries finds drafts.', (string) $url));
		}

		return $entry;
	}

	/**
	 * Whether a context sees an entry: one anyone may read, or one it may
	 * edit (or, in the trash, delete).
	 */
	public function sees(McpContext $context, Entry $entry): bool
	{
		if (! isset($this->types($context)[$entry->type->name])) {
			return false;
		}

		if ($entry->status === Status::Trash) {
			return $context->can(ContentAction::Delete, $entry);
		}

		return ($entry->type->public && $entry->isPublished() && $entry->visibility !== Visibility::Hidden)
			|| $context->can(ContentAction::Edit, $entry);
	}

	/**
	 * Returns an entry's stub: its id, type, title, status, address,
	 * dates, and excerpt.
	 *
	 * @return array<string, mixed>
	 */
	public function stub(Entry $entry): array
	{
		return [
			'id'        => $entry->id,
			'type'      => $entry->type->name,
			'title'     => $entry->title,
			'status'    => $entry->status->value,
			'url'       => $entry->isPublished() ? $this->api->url($entry) : null,
			'published' => $entry->published?->format(DATE_ATOM),
			'updated'   => $entry->updated->format(DATE_ATOM),
			'excerpt'   => trim(strip_tags($entry->excerpt(more: '')))
		];
	}

	/**
	 * Returns an entry in full: the API's item (D-739), with its Markdown
	 * body as it's written, its revision, and links to its editor and a
	 * preview.
	 *
	 * @return array<string, mixed>
	 * @throws ToolError When it can't be read.
	 */
	public function item(McpContext $context, Entry $entry, bool $editing = true): array
	{
		try {
			$item = $this->resource->item($entry, 'none', new ApiIncludes(relations: false));
			$file = $this->content->editable($entry);
		} catch (MediaException | WriteException $error) {
			throw new ToolError(sprintf('“%s” can\'t be read: %s', $entry->title, $error->getMessage()));
		}

		$item['status'] = $entry->status->value;
		$item['url']    = $entry->isPublished() ? $this->api->url($entry) : null;

		if ($editing && $context->can(ContentAction::Edit, $entry)) {
			$item['revision'] = $file->version;
			$item['edit_url'] = $this->admin->entry($entry);
			$item['preview']  = $this->preview($entry);
		}

		// The body as written, so a passage copied from it is found again.
		$item['body'] = substr($file->body, strlen(DocumentEditor::gap($file->body)));

		return $item;
	}

	/**
	 * Returns a signed link to an entry's preview (D-226), or `null` when
	 * previews are off.
	 */
	public function preview(Entry $entry): ?string
	{
		try {
			return $this->previews->make($entry)->url;
		} catch (LogicException) {
			return null;
		}
	}
}
