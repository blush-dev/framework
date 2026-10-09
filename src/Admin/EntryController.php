<?php

/**
 * Admin entry controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use JsonException;
use LogicException;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Auth\Account;
use Blush\Auth\AccountStore;
use Blush\Auth\AuthException;
use Blush\Auth\Capability;
use Blush\Auth\ContentAction;
use Blush\Auth\Permissions;
use Blush\Content\Entries;
use Blush\Content\Entry\Entry;
use Blush\Content\Entry\Position;
use Blush\Content\EntryFields;
use Blush\Storage\Record\Order;
use Blush\Content\Lint\Linter;
use Blush\Content\Relation\LinkResolver;
use Blush\Content\Relation\ProblemKind;
use Blush\Content\Relation\Referrers;
use Blush\Content\Relation\Relation;
use Blush\Content\Relation\RelationLimits;
use Blush\Content\Relation\RelationProblem;
use Blush\Content\Relation\Relations;
use Blush\Content\Relation\TranslationRule;
use Blush\Content\RelationArchives;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Status as EntryStatus;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\DateArchives;
use Blush\Content\Type\Profiles;
use Blush\Content\Type\Tree;
use Blush\Content\TypedTargets;
use Blush\Content\Writer\DocumentEditor;
use Blush\Content\Writer\EditableEntry;
use Blush\Content\Writer\EntryChanges;
use Blush\Content\Writer\WriteConflict;
use Blush\Content\Writer\WriteException;
use Blush\Core\AppConfig;
use Blush\Field\Field;
use Blush\Field\FieldSet;
use Blush\Field\FieldTargets;
use Blush\Field\Violation;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Support\Slug;

/**
 * The admin's editing API (D-229), over `Entries`' writes by id (D-654):
 *
 * - `GET    entries/{id}`: an entry for editing, by its id (D-481; a
 *   file without one can't be edited until it has one): its source
 *   `path`, its field values by name
 *   (from whichever key or alias the file uses), other front matter,
 *   body, revision, when the file was last written (`modified`), type
 *   and field descriptions, what the account may do, and the file's
 *   problems, and its handle (`EntryHandles`, D-253), or `null`.
 * - `GET    entries/new?type=…`: a new entry of a type, described the same
 *   way but not yet written (no `path`, `id`, `handle`, or `revision`), so the
 *   editor opens on it and its first save creates it (D-336). It's a
 *   draft crediting the account's author, with nothing else filled in.
 * - `POST   entries`: creates one (`type`, `title`, optional `slug`,
 *   `set`, `body`, `status`). New entries are drafts unless asked
 *   otherwise, and credit the account's author. A tree's page may name
 *   a `parent` (another of its pages, by key) to go under (D-408); a
 *   parent kept as a file becomes its folder's page, keeping its
 *   address. A parent that isn't there is a 422 with `field: "parent"`.
 * - `PATCH  entries/{id}`: changes one (`revision` required; `set`,
 *   `remove`, `body`, `status`, `published`, `slug`, `redirect`). A new
 *   `slug` renames it (D-277): its `slug` key when the file has one,
 *   else the file, first, so a slug that's refused (not a slug, another
 *   entry's, or a landing page's) changes nothing; the refusal is a 422
 *   with `field: "slug"`. With `redirect: true`, a published entry's old
 *   address is added to its `redirect_from`. A tree's page takes `parent`
 *   (another of its pages, by key, or `""` for the top) to move under
 *   it (D-410), with the pages under it; a parent that isn't there, or a
 *   page already at the new place, is a 422 with `field: "parent"`. When
 *   a tree's page moves or is renamed with `redirect: true`, each
 *   published page under it gets a redirect from its old address too.
 *   A new publish date renames a file its type's own pattern names by
 *   date (`FileNames::follow()`, D-519); bulk publishing an undated
 *   entry does too.
 * - `DELETE entries/{id}?revision=…`: moves it to the trash (D-484):
 *   it stays where it is, with `status: trash`, off the site, keeping
 *   its address and id, answering `{"trashed", "restore"}`, what an
 *   Undo sends to restore it. `DELETE entries/{id}?permanently=1` deletes an
 *   entry that's in the trash for good (and only one that is); with
 *   `unlink=1`, it's first taken out of the entries linking to it that
 *   the account may edit (D-598), answering how many (`unlinked`).
 * - `POST   entries/referrers` (`{"ids"}`, at most 100): of those the
 *   account may edit, the live ones live entries link to, for a bulk
 *   change's warning (`linked`: `id`, `title`, and how many `live`),
 *   most linked first (D-608).
 * - `GET    entries/{id}/referrers`: what links to an entry (D-598):
 *   `count`, how many are `live`, how many are drafts (`drafts`,
 *   scheduled ones too), how many the account may edit (`editable`),
 *   how many live ones credit it alone in their byline
 *   (`uncredited`, D-608), and the first 8 `entries` (`id`, `title`,
 *   `type`, its singular label; `status`; and the `relations` they link
 *   through, by label), only live ones with `live=1`.
 * - `POST   entries/{id}/restore`: brings one back from the trash **as a
 *   draft** (D-237), so it's never republished without someone choosing
 *   to. An Undo sends the `restore` that moving it to the trash answered
 *   (`{"status", "revision"}`), which puts back the status its file had
 *   (`null` for none, so published), and only while it's unchanged since
 *   (D-525).
 * - `POST   entries/empty-trash` (`{"type"?}`): deletes for good every
 *   entry in the trash the account may delete (of one type, when
 *   given), answering how many (`deleted`).
 * - `POST   entries/bulk`: publishes, moves to draft, or trashes several
 *   (`action`: `publish`, `draft`, or `trash`; `ids`, at most 100) at
 *   their current revisions, since a status change or a move to the
 *   trash loses no one's writing (D-301). Each entry is checked as alone:
 *   one that can't be changed (not allowed, an index page to trash, a
 *   required field empty or a relation out of its limits for
 *   publishing, a write that fails) is skipped
 *   with the reason, and the rest go ahead. Answers `done` (ids) and
 *   `skipped` (`id`, `title`, `reason`).
 * - `POST   entries/{id}/duplicate`: copies it beside itself as a draft
 *   titled "… (Copy)", slugged `{slug}-copy`, with the same authors,
 *   dated now if its type is dated (D-275). Needs to create entries of
 *   its type, and to edit the entry; a landing page (an index page or the homepage) can't be
 *   copied.
 *
 * An entry in the trash is looked at, not edited: `GET` answers it to
 * whoever may delete it (with `can.edit` false and its `trashed` date),
 * and changing or copying it is refused until it's restored.
 *
 * **Permissions come from the change, not only the entry,** and are the
 * entry's type's (D-359, `content.{type}.edit` and so on): editing needs
 * to edit the entry (with D-219's ownership and live-entry rules); a
 * result that isn't a draft needs to publish it; changing the authors so
 * the account's own author isn't among them needs to edit anyone's;
 * creating needs to create entries of the type; deleting needs to
 * delete the entry.
 *
 * **Relations are checked before a write** (D-596): a value a save adds
 * in a relation that creates its targets (a tag), naming no entry, is
 * written as one, published and titled as typed, first, so it's linked
 * by id; when the account may not create and publish entries of that
 * type, the save is a 403 with the relation's `field`. A save that
 * leaves the entry live (as publishing does) with a relation below its
 * `min`, above its `max`, or naming a target already named by its
 * inverse's `max` is a 422 with the relation's `field` (and, for that
 * last, `full`: the target, and another with room, D-608); bulk publishing
 * skips such an entry.
 *
 * `status` is a shortcut: `draft` sets `status: draft`; `published`
 * clears it and dates the entry now if it has no date or a future one;
 * `scheduled` clears it and sets `published` (a future date, required).
 *
 * **A type's index page** (`IndexPage`, D-274) is edited without the
 * type's fields: only its title and status are, and everything else in its
 * front matter is kept as it is (`extra`). It's never scheduled or
 * dated by publishing, and never moved to the trash.
 */
final readonly class EntryController
{
	/**
	 * The statuses the shortcut takes.
	 */
	private const array STATUSES = ['draft', 'published', 'scheduled'];

	/**
	 * What a bulk change can do, and the most entries it takes.
	 */
	private const array BULK_ACTIONS = ['publish', 'draft', 'trash'];

	private const int BULK_LIMIT = 100;

	/**
	 * How many entries a list of what links to an entry names (D-608),
	 * and up to how many a Linked From group holds all of, to show in
	 * place.
	 */
	private const int LISTED = 8;

	private const int LISTED_WHOLE = 50;

	public function __construct(
		private Entries $content,
		private ContentTypes $types,
		private ContentUrls $urls,
		private Linter $linter,
		private EntryHandles $handles,
		private Permissions $permissions,
		private AppConfig $app,
		private ClockInterface $clock,
		private FieldTargets $targets,
		private AccountStore $accounts,
		private Homepage $homepage,
		private HtmlGuard $html,
		private Relations $relations,
		private RelationLimits $limits,
		private RelationArchives $relationArchives,
		private TypedTargets $typed,
		private Referrers $referrers,
		private ArchivePages $archivePages
	) {}

	/**
	 * Answers an entry for editing.
	 */
	public function show(ServerRequestInterface $request, string $id): ResponseInterface
	{
		$entry = $this->content->find($id);

		if ($entry === null) {
			return self::error(sprintf('There\'s no entry with the id "%s".', $id), Status::NotFound);
		}

		return $this->edit($request, $entry);
	}

	/**
	 * Answers a new entry of a type, not yet written, for the editor to
	 * open on (D-336).
	 */
	public function blank(ServerRequestInterface $request): ResponseInterface
	{
		$account = self::account($request);
		$name    = $request->getQueryParams()['type'] ?? null;
		$type    = is_string($name) ? $this->types->find($name) : null;

		if ($type === null) {
			return $this->permissions->can($account, ContentAction::Create)
				? self::error('Send a known "type".', Status::BadRequest)
				: self::error('You aren\'t allowed to create entries.', Status::Forbidden);
		}

		if (! $this->permissions->can($account, ContentAction::Create, $type->name)) {
			return self::error(sprintf('You aren\'t allowed to create %s.', $type->labels->items), Status::Forbidden);
		}

		$fields = array_filter($this->types->schema($type->name)->fields, fn (Field $field): bool => $this->groups($field, $type, []) && ! in_array($field->name, [EntryFields::TRASHED, EntryFields::TRANSLATION_OF], true));

		return self::json([
			'path'        => null,
			'id'          => null,
			'handle'      => null,
			'slug'        => '',
			'key'         => '',
			'parent'      => $type instanceof Tree ? '' : null,
			'revision'    => null,
			'modified'    => null,
			'title'       => '',
			'status'      => EntryStatus::Draft->value,
			'own'         => true,
			'url'         => null,
			'type'        => $this->typeOf($type, $fields),
			'index'       => false,
			'archivePage' => false,
			'archive'     => null,
			'introduces'  => null,
			'errorPage'   => null,
			'homepage'    => false,
			'rootPage'    => false,
			'homeInstead' => null,
			'values'      => $this->authorDefault($account, $type->name),
			'extra'       => [],
			'body'        => '',
			'can'         => [
				'edit'         => true,
				'publish'      => $this->permissions->can($account, ContentAction::Publish, $type->name),
				'rename'       => true,
				'move'         => false,
				'delete'       => false,
				'duplicate'    => false,
				'makeHomepage' => false
			],
			'violations'  => []
		]);
	}

	/**
	 * Answers an entry for editing, if the account may edit it.
	 */
	private function edit(ServerRequestInterface $request, Entry $entry): ResponseInterface
	{
		$account = self::account($request);
		$action  = $entry->status === EntryStatus::Trash ? ContentAction::Delete : ContentAction::Edit;

		if (! $this->permissions->can($account, $action, $entry)) {
			return self::error('You aren\'t allowed to edit that entry.', Status::Forbidden);
		}

		try {
			return self::json($this->describe($account, $entry, $this->content->editable($entry)));
		} catch (WriteException $e) {
			return self::error($e->getMessage(), Status::UnprocessableContent);
		}
	}

	/**
	 * Creates an entry.
	 */
	public function create(ServerRequestInterface $request): ResponseInterface
	{
		$account = self::account($request);
		$input   = self::input($request);

		$type  = is_string($input['type'] ?? null) ? $this->types->find($input['type']) : null;
		$title = $input['title'] ?? null;

		if (! $this->permissions->can($account, ContentAction::Create, $type?->name)) {
			return self::error($type === null ? 'You aren\'t allowed to create entries.' : sprintf('You aren\'t allowed to create %s.', $type->labels->items), Status::Forbidden);
		}

		if ($type === null || ! is_string($title) || trim($title) === '') {
			return self::error('Send a known "type" and a "title".', Status::BadRequest);
		}

		$slug = is_string($input['slug'] ?? null) && $input['slug'] !== '' ? $input['slug'] : Slug::from($title);
		$now  = $this->clock->now()->setTimezone($this->app->timezone());

		try {
			$status = self::stringOr($input, 'status', 'draft');
			$set    = [
				'title' => $title,
				'published' => self::dateString($now),
				...$this->authorDefault($account, $type->name),
				...self::map($input, 'set')
			];

			$body    = self::stringOr($input, 'body', '');

			// The editor's body starts at its first line (D-334); the
			// file has a blank line before it.
			$changes = $this->withStatus(new EntryChanges(set: $set, body: DocumentEditor::gap($body) === '' ? "\n{$body}" : $body), $status, $input, null);
		} catch (InvalidEdit $e) {
			return self::error($e->getMessage(), Status::BadRequest);
		} catch (WriteException $e) {
			return self::error($e->getMessage(), Status::UnprocessableContent);
		}

		if (! self::isDraft($changes, null) && ! $this->permissions->can($account, ContentAction::Publish, $type->name)) {
			return self::error('You aren\'t allowed to publish, so new entries must be drafts.', Status::Forbidden);
		}

		$html = $this->html->refusal($account, '', $changes->body ?? '');

		if ($html !== null) {
			return self::error($html, Status::Forbidden, 'body');
		}

		$parentKey = $input['parent'] ?? null;
		$parent    = null;

		if ($parentKey !== null && $parentKey !== '') {
			if (! is_string($parentKey) || ! $type instanceof Tree) {
				return self::error(sprintf('Only pages of a tree go under another; %s don\'t.', $type->labels->items), Status::BadRequest);
			}

			$parent = $this->content->named($type->name, $parentKey);

			if ($parent === null || $parent->landing) {
				return self::error(sprintf('There\'s no "%s" in %s to put it under.', $parentKey, $type->labels->items), Status::UnprocessableContent, 'parent');
			}
		}

		$related = $this->related($account, $type, null, [], $changes);

		if ($related !== null) {
			return $related;
		}

		try {
			$entry = $this->content->create($type, $slug, $changes, $parent, $now);
			$file  = $this->content->editable($entry);
		} catch (WriteException $e) {
			// A trashed entry keeps its address until it's deleted (D-484).
			$holder = $this->content->named($type->name, $parent === null ? $slug : "{$parent->key}/{$slug}");

			return self::error($holder?->status === EntryStatus::Trash
				? sprintf('“%s” in the trash has this address; restore it, or delete it permanently, first.', trim($holder->title) === '' ? 'Untitled' : $holder->title)
				: $e->getMessage(), Status::UnprocessableContent);
		}

		$this->remember($account, $entry);

		return self::json($this->describe($account, $entry, $file), Status::Created);
	}

	/**
	 * Changes an entry.
	 */
	public function update(ServerRequestInterface $request, string $id): ResponseInterface
	{
		$account = self::account($request);
		$input   = self::input($request);
		$entry   = $this->content->find($id);

		if ($entry === null) {
			return self::error(sprintf('There\'s no entry with the id "%s".', $id), Status::NotFound);
		}

		if (! $this->permissions->can($account, ContentAction::Edit, $entry)) {
			return self::error('You aren\'t allowed to edit that entry.', Status::Forbidden);
		}

		if ($entry->status === EntryStatus::Trash) {
			return self::error(self::inTrash($entry), Status::UnprocessableContent);
		}

		$revision = $input['revision'] ?? null;

		if (! is_string($revision)) {
			return self::error('Send the "revision" you loaded, so no one else\'s change is lost.', Status::PreconditionRequired);
		}

		try {
			$body    = $input['body'] ?? null;
			$changes = new EntryChanges(set: self::map($input, 'set'), remove: self::list($input, 'remove'), body: is_string($body) ? $body : null);
			$status  = $input['status'] ?? null;
			$changes = $status === null ? $changes : $this->withStatus($changes, is_string($status) ? $status : '', $input, $entry);
			$slug    = $input['slug'] ?? null;
		} catch (InvalidEdit $e) {
			return self::error($e->getMessage(), Status::BadRequest);
		} catch (WriteException $e) {
			return self::error($e->getMessage(), Status::UnprocessableContent);
		}

		$refusal = $this->refusal($account, $entry, $changes);

		if ($refusal !== null) {
			return self::error($refusal, Status::Forbidden);
		}

		try {
			$file = $this->content->editable($entry);
		} catch (WriteException $e) {
			return self::error($e->getMessage(), Status::UnprocessableContent);
		}

		// Only the HTML the body didn't have counts (D-495).
		if ($changes->body !== null) {
			$html = $this->html->refusal($account, $file->body, $changes->body);

			if ($html !== null) {
				return self::error($html, Status::Forbidden, 'body');
			}
		}

		// A move (D-410): where to, by the new parent's key (`''` for the
		// top), or `false` for staying put.
		$move = false;

		if (array_key_exists('parent', $input)) {
			$asked = $input['parent'];

			if (! is_string($asked) || ! $entry->type instanceof Tree || $entry->landing || ErrorPage::status($entry) !== null) {
				return self::error(sprintf('Only a tree\'s pages move under another; "%s" doesn\'t.', $entry->title), Status::BadRequest);
			}

			$target = $asked === '' ? null : $this->content->named($entry->type->name, $asked);

			if ($asked !== '' && ($target === null || $target->landing)) {
				return self::error(sprintf('There\'s no "%s" in %s to move it under.', $asked, $entry->type->labels->items), Status::UnprocessableContent, 'parent');
			}

			if ($entry->type->parentKey($entry->key, []) !== $target?->key) {
				$move = $target;
			}
		}

		$rename  = is_string($slug) && $slug !== '' && $slug !== $entry->slug ? $slug : null;
		$folder  = $move === false ? dirname($entry->key) : ($move === null ? '.' : $move->key);
		$problem = $rename === null ? null : ($this->isLinked($entry) ? 'An account is linked to this profile by its slug, so the slug stays.' : $this->slugProblem($entry, $rename, $folder));

		if ($problem !== null) {
			return self::error($problem, Status::UnprocessableContent, 'slug');
		}

		if ($move !== false && $rename === null) {
			$problem = $this->slugProblem($entry, $entry->slug, $folder);

			if ($problem !== null) {
				return self::error(sprintf('There\'s already a page at %s.', ($folder === '.' ? '' : "{$folder}/") . $entry->slug), Status::UnprocessableContent, 'parent');
			}
		}

		$redirect = ($input['redirect'] ?? false) === true;

		// The pages under a tree's page, whose addresses change with its.
		$under = $redirect && ($move !== false || $rename !== null) && $entry->type instanceof Tree
			? array_values(array_filter(
				$this->content->query()->any()->type($entry->type->name)->limit(null)->get()->all(),
				static fn (Entry $item): bool => str_starts_with($item->key, "{$entry->key}/")
			))
			: [];

		// The old address can redirect to the new one.
		if ($move !== false || $rename !== null) {
			$changes = $this->withRedirect($changes, $entry, $file, $redirect);
		}

		try {
			$related = $this->related($account, $entry->type, $entry, $file->frontMatter, $changes);
		} catch (WriteException $e) {
			return self::error($e->getMessage(), Status::UnprocessableContent);
		}

		if ($related !== null) {
			return $related;
		}

		try {
			// Moving and renaming first, so a refused place or name leaves
			// it as it was. Where that puts a file, and whether a new slug
			// or date renames it, is the storage driver's (D-654).
			if ($move !== false) {
				$revision = $this->content->move($entry, $move, $revision)->version ?? $revision;
			}

			if ($rename !== null) {
				$revision = $this->content->rename($entry, $rename, $revision)->version ?? $revision;
			}

			$updated = $this->content->change($entry, $changes, $revision);
			$file    = $this->content->editable($updated);
		} catch (WriteConflict $e) {
			return self::error($e->getMessage(), Status::Conflict);
		} catch (WriteException $e) {
			return self::error($e->getMessage(), Status::UnprocessableContent);
		}

		$this->redirectUnder($entry, $updated, $under);
		$this->remember($account, $updated);

		return self::json($this->describe($account, $updated, $file));
	}

	/**
	 * Keeps the entry as the one the account last saved, for the
	 * dashboard's "You Were Editing" (D-539). The account's file is
	 * written only when that changes.
	 */
	private function remember(Account $account, Entry $entry): void
	{
		if ($entry->id !== null && $account->preferences->lastEdited !== $entry->id) {
			$this->accounts->save($account->withPreferences($account->preferences->withLastEdited($entry->id)));
		}
	}

	/**
	 * Adds a redirect from each published page's old address, under a
	 * tree's page that moved or was renamed, to its new one (D-410). The
	 * page itself is saved by then, so one that can't take its redirect
	 * is left as it is.
	 *
	 * @param list<Entry> $under The pages that were under it, as they were.
	 */
	private function redirectUnder(Entry $was, Entry $now, array $under): void
	{
		foreach ($under as $page) {
			$found = $this->content->named($now->type->name, $now->key . substr($page->key, strlen($was->key)));

			if ($found === null) {
				continue;
			}

			try {
				$this->content->change($found, $this->withRedirect(new EntryChanges(), $page, $this->content->editable($found), true));
			} catch (WriteException) {
				// The move stands; this page keeps working at its new address.
			}
		}
	}

	/**
	 * Copies an entry as a draft (D-275).
	 */
	public function duplicate(ServerRequestInterface $request, string $id): ResponseInterface
	{
		$account = self::account($request);
		$entry   = $this->content->find($id);

		if ($entry === null) {
			return self::error(sprintf('There\'s no entry with the id "%s".', $id), Status::NotFound);
		}

		if (! $this->permissions->can($account, ContentAction::Create, $entry->type->name) || ! $this->permissions->can($account, ContentAction::Edit, $entry)) {
			return self::error('You aren\'t allowed to duplicate that entry.', Status::Forbidden);
		}

		if ($entry->status === EntryStatus::Trash) {
			return self::error(self::inTrash($entry), Status::UnprocessableContent);
		}

		if (IndexPage::is($entry)) {
			return self::error(sprintf('"%s" is the index page for %s, and there\'s only one.', $entry->title, $entry->type->labels->items), Status::UnprocessableContent);
		}

		if (Homepage::isRootPage($entry)) {
			return self::error(sprintf('"%s" is the site\'s root page, and there\'s only one.', $entry->title), Status::UnprocessableContent);
		}

		$now   = $this->clock->now()->setTimezone($this->app->timezone());
		$title = sprintf('%s (Copy)', trim($entry->title) === '' ? 'Untitled' : $entry->title);
		$set   = [
			'title'     => $title,
			'status'    => 'draft',
			'published' => self::dateString($now)
		];

		try {
			// The copy's name is its file's, and the original's old
			// addresses stay the original's.
			$copy = $this->content->duplicate($entry, Slug::from(($entry->slug === '' ? $title : $entry->slug) . '-copy'), new EntryChanges(set: $set, remove: ['slug', 'redirect_from']), $now);
			$file = $this->content->editable($copy);
		} catch (WriteException $e) {
			return self::error($e->getMessage(), Status::UnprocessableContent);
		}

		return self::json($this->describe($account, $copy, $file), Status::Created);
	}

	/**
	 * Moves an entry to the trash, or deletes one in the trash for good.
	 */
	public function delete(ServerRequestInterface $request, string $id): ResponseInterface
	{
		$account     = self::account($request);
		$entry       = $this->content->find($id);
		$params      = $request->getQueryParams();
		$revision    = $params['revision'] ?? null;
		$permanently = in_array($params['permanently'] ?? null, ['1', 'true'], true);

		if ($entry === null) {
			return self::error(sprintf('There\'s no entry with the id "%s".', $id), Status::NotFound);
		}

		if (! $this->permissions->can($account, ContentAction::Delete, $entry)) {
			return self::error('You aren\'t allowed to delete that entry.', Status::Forbidden);
		}

		$trashed = $entry->status === EntryStatus::Trash;

		if ($trashed !== $permanently) {
			return self::error($trashed
				? sprintf('"%s" is already in the trash; delete it permanently, or restore it.', $entry->title)
				: sprintf('"%s" isn\'t in the trash; move it there first.', $entry->title), Status::UnprocessableContent);
		}

		if (IndexPage::is($entry)) {
			return self::error(sprintf('"%s" is the index page for %s, so it can\'t be moved to the trash.', $entry->title, $entry->type->labels->items), Status::UnprocessableContent);
		}

		if (! $trashed && ! is_string($revision)) {
			return self::error('Send the "revision" you loaded, so no one else\'s change is lost.', Status::PreconditionRequired);
		}

		try {
			if ($trashed) {
				// Taken out of what links to it first (D-598), where the
				// account may edit, so nothing names it after.
				[$unlinked] = in_array($params['unlink'] ?? null, ['1', 'true'], true)
					? $this->referrers->unlink($entry, fn (Entry $referrer): bool => $this->permissions->can($account, ContentAction::Edit, $referrer))
					: [[]];

				$this->content->delete($entry);

				return self::json(['deleted' => $id, 'unlinked' => count($unlinked)]);
			}

			$declared = $this->content->editable($entry)->frontMatter['status'] ?? null;
			$trashed  = $this->content->trash($entry, $revision);
		} catch (WriteConflict $e) {
			return self::error($e->getMessage(), Status::Conflict);
		} catch (WriteException $e) {
			return self::error($e->getMessage(), Status::UnprocessableContent);
		}

		// What an Undo sends to put it back as it was (D-525).
		return self::json(['trashed' => $id, 'restore' => [
			'status'   => is_string($declared) ? $declared : null,
			'revision' => $trashed->version
		]]);
	}

	/**
	 * Answers what links to an entry (D-598), for the admin to say before
	 * it leaves the site: how many entries do, how many of them are live,
	 * how many the account may edit, and the first few.
	 */
	public function referrers(ServerRequestInterface $request, string $id): ResponseInterface
	{
		$account = self::account($request);
		$entry   = $this->content->find($id);

		if ($entry === null) {
			return self::error(sprintf('There\'s no entry with the id "%s".', $id), Status::NotFound);
		}

		if (! $this->permissions->can($account, ContentAction::Edit, $entry) && ! $this->permissions->can($account, ContentAction::Delete, $entry)) {
			return self::error('You aren\'t allowed to change that entry.', Status::Forbidden);
		}

		$sources = $this->referrers->sources($entry);
		$found   = array_column($sources, 'entry');
		$listed  = in_array($request->getQueryParams()['live'] ?? null, ['1', 'true'], true)
			? array_filter($sources, static fn (array $source): bool => $source['entry']->isPublished())
			: $sources;

		return self::json([
			'count'      => count($found),
			'live'       => Referrers::live($found),
			'drafts'     => count(array_filter($found, static fn (Entry $referrer): bool => in_array($referrer->status, [EntryStatus::Draft, EntryStatus::Scheduled], true))),
			'editable'   => count(array_filter($found, fn (Entry $referrer): bool => $this->permissions->can($account, ContentAction::Edit, $referrer))),
			'uncredited' => $this->referrers->soleCredits($entry),
			'entries'    => array_map(static fn (array $source): array => [
				'id'        => $source['entry']->id,
				'title'     => $source['entry']->title,
				'type'      => $source['entry']->type->labels->singular,
				'status'    => $source['entry']->status->value,
				'relations' => array_map(self::relationLabel(...), $source['relations'])
			], array_slice(array_values($listed), 0, self::LISTED))
		]);
	}

	/**
	 * Answers which of several entries live entries link to (D-598), for
	 * a bulk move to draft or the trash: only live ones the account may
	 * edit, each with how many live entries link to it.
	 */
	public function manyReferrers(ServerRequestInterface $request): ResponseInterface
	{
		$account = self::account($request);
		$ids     = self::list(self::input($request), 'ids');

		if ($ids === [] || count($ids) > self::BULK_LIMIT) {
			return self::error(sprintf('Send "ids": from 1 to %d entry ids.', self::BULK_LIMIT), Status::BadRequest);
		}

		$linked = [];

		foreach ($ids as $id) {
			$entry = $this->content->find($id);

			if ($entry === null || ! $entry->isPublished() || ! $this->permissions->can($account, ContentAction::Edit, $entry)) {
				continue;
			}

			$live = Referrers::live($this->referrers->of($entry));

			if ($live > 0) {
				$linked[] = ['id' => $id, 'title' => $entry->title, 'live' => $live];
			}
		}

		// Most linked first, so a capped list shows what matters.
		usort($linked, static fn (array $a, array $b): int => $b['live'] <=> $a['live']);

		return self::json(['linked' => $linked]);
	}

	/**
	 * Brings an entry back from the trash as a draft, or, for an Undo, with
	 * the status it had, at the revision moving it there wrote (D-525).
	 */
	public function restore(ServerRequestInterface $request, string $id): ResponseInterface
	{
		$account  = self::account($request);
		$entry    = $this->content->find($id);
		$input    = self::input($request);
		$revision = $input['revision'] ?? null;

		// No `status` is a draft; `null` is none in the file, so published.
		$status = match (true) {
			! array_key_exists('status', $input) => EntryStatus::Draft,
			$input['status'] === null            => null,
			is_string($input['status'])          => EntryStatus::tryFrom($input['status']) ?? false,
			default                              => false
		};

		if ($entry === null || $entry->status !== EntryStatus::Trash) {
			return self::error('That isn\'t in the trash.', Status::NotFound);
		}

		if (! $this->permissions->can($account, ContentAction::Delete, $entry)) {
			return self::error('You aren\'t allowed to restore that entry.', Status::Forbidden);
		}

		if ($status === false || $status === EntryStatus::Trash || $status === EntryStatus::Scheduled) {
			return self::error('An entry is restored as "draft", "published", or `null` (no status, so published).', Status::UnprocessableContent);
		}

		if ($status !== EntryStatus::Draft && ! is_string($revision)) {
			return self::error('Send the "revision" moving it to the trash answered, so it\'s put back only as it was.', Status::PreconditionRequired);
		}

		if ($status !== EntryStatus::Draft && ! $this->permissions->can($account, ContentAction::Publish, $entry)) {
			return self::error('You aren\'t allowed to publish that entry, so it can\'t be put back as it was; restore it as a draft.', Status::Forbidden);
		}

		try {
			$this->content->restore($entry, is_string($revision) ? $revision : null, $status);
		} catch (WriteConflict) {
			return self::error(sprintf('"%s" changed in the trash since it was moved there, so it can\'t be put back as it was; restore it from the Trash.', $entry->title), Status::Conflict);
		} catch (WriteException $e) {
			return self::error($e->getMessage(), Status::UnprocessableContent);
		}

		return self::json(['id' => $id]);
	}

	/**
	 * Deletes for good every entry in the trash the account may delete.
	 */
	public function emptyTrash(ServerRequestInterface $request): ResponseInterface
	{
		$account = self::account($request);
		$type    = self::input($request)['type'] ?? null;

		if ($type !== null && (! is_string($type) || ! $this->types->has($type))) {
			return self::error('There is no such content type.', Status::BadRequest);
		}

		$query   = $this->content->query()->any()->status(EntryStatus::Trash)->limit(null);
		$query   = $type === null ? $query : $query->type($type);
		$deleted = 0;

		foreach ($this->permissions->restrict($account, ContentAction::Delete, $query)->get() as $entry) {
			try {
				$this->content->delete($entry);
				$deleted++;
			} catch (WriteException $e) {
				return self::json(['error' => $e->getMessage(), 'deleted' => $deleted], Status::UnprocessableContent);
			}
		}

		return self::json(['deleted' => $deleted]);
	}

	/**
	 * Returns when an entry was moved to the trash, or `null`.
	 */
	public static function trashed(Entry $entry): ?string
	{
		$trashed = $entry->field(EntryFields::TRASHED);

		return $entry->status === EntryStatus::Trash && $trashed instanceof DateTimeInterface ? $trashed->format(DateTimeInterface::ATOM) : null;
	}

	/**
	 * Says that an entry is in the trash, so it can't be changed.
	 */
	private static function inTrash(Entry $entry): string
	{
		return sprintf('"%s" is in the trash; restore it to change it.', trim($entry->title) === '' ? 'Untitled' : $entry->title);
	}

	/**
	 * Publishes, moves to draft, or trashes several entries (D-301).
	 */
	public function bulk(ServerRequestInterface $request): ResponseInterface
	{
		$account = self::account($request);
		$input   = self::input($request);
		$action  = $input['action'] ?? null;
		$ids     = $input['ids'] ?? null;

		if (! in_array($action, self::BULK_ACTIONS, true)) {
			return self::error(sprintf('"action" must be one of %s.', implode(', ', self::BULK_ACTIONS)), Status::BadRequest);
		}

		$given = is_array($ids) ? array_filter($ids, is_string(...)) : [];

		if ($given === [] || count($given) !== count((array) $ids) || count($given) > self::BULK_LIMIT) {
			return self::error(sprintf('"ids" must list from 1 to %d entry ids.', self::BULK_LIMIT), Status::BadRequest);
		}

		$done    = [];
		$skipped = [];

		foreach (array_values(array_unique($given)) as $id) {
			$entry  = $this->content->find($id);
			$reason = $entry === null ? 'It\'s no longer there.' : $this->bulkChange($account, $entry, $action);

			if ($reason === null) {
				$done[] = $id;
			} else {
				$skipped[] = ['id' => $id, 'title' => $entry->title ?? '', 'reason' => $reason];
			}
		}

		return self::json(['action' => $action, 'done' => $done, 'skipped' => $skipped]);
	}

	/**
	 * Makes one entry's part of a bulk change, returning why it couldn't
	 * be made, or `null` once it's made.
	 */
	private function bulkChange(Account $account, Entry $entry, string $action): ?string
	{
		if ($entry->status === EntryStatus::Trash) {
			return 'It\'s in the trash already; restore it to change it.';
		}

		if ($action === 'trash') {
			if (! $this->permissions->can($account, ContentAction::Delete, $entry)) {
				return 'You aren\'t allowed to delete it.';
			}

			if (IndexPage::is($entry)) {
				return 'It\'s an index page, so it can\'t be moved to the trash.';
			}
		} elseif (! $this->permissions->can($account, ContentAction::Edit, $entry)) {
			return 'You aren\'t allowed to edit it.';
		}

		try {
			$revision = $this->content->editable($entry)->version;

			if ($action === 'trash') {
				$this->content->trash($entry, $revision);

				return null;
			}

			$changes = $this->withStatus(new EntryChanges(), $action === 'publish' ? 'published' : 'draft', [], $entry);
			$refusal = $this->refusal($account, $entry, $changes) ?? ($action === 'publish' ? $this->missing($entry) ?? $this->outOfLimits($entry) : null);

			if ($refusal !== null) {
				return $refusal;
			}

			// Publishing an undated entry dates it, which may rename its
			// file (D-519), the storage driver's to do.
			$this->content->change($entry, $changes, $revision);
		} catch (InvalidEdit | WriteException $e) {
			return $e->getMessage();
		}

		return null;
	}

	/**
	 * Says which required fields an entry leaves empty, which keeps it
	 * from being published (the editor's rule, admin.md §8 Validation),
	 * or `null` when none are. An index page is edited without the type's
	 * fields, so it has none; the publish date isn't checked, since
	 * publishing sets it.
	 */
	private function missing(Entry $entry): ?string
	{
		$empty = IndexPage::is($entry) ? [] : array_filter($entry->type->schema->fields, static function (Field $field) use ($entry): bool {
			$value = $field->name === 'title' ? $entry->title : $entry->field($field->name);

			return $field->required
				&& $field->name !== 'published'
				&& ($value === null || $value === [] || (is_string($value) && trim($value) === ''));
		});

		$names = array_values(array_map(static fn (Field $field): string => $field->label === '' ? ucfirst($field->name) : $field->label, $empty));

		return match (count($names)) {
			0       => null,
			1       => sprintf('%s is required to publish.', $names[0]),
			default => sprintf('%s are required to publish.', implode(', ', $names))
		};
	}

	/**
	 * Checks a save's relations (D-596), and writes the targets it creates
	 * as they're typed, before the entry is written. Refused, as an error
	 * to answer, when the account may not create and publish entries of
	 * a target's type, or when a save that leaves the entry live leaves a
	 * relation out of its limits; `null` once it may go ahead.
	 *
	 * @param array<array-key, mixed> $before The entry's front matter, none for a new one.
	 */
	private function related(Account $account, ContentType $type, ?Entry $entry, array $before, EntryChanges $changes): ?ResponseInterface
	{
		$after    = array_diff_key([...$before, ...$changes->set], array_flip($changes->remove));
		$language = $entry->language ?? $this->app->languages->default->code;
		$found    = $this->typed->find($type->name, $language, $before, $after);

		foreach ($found as $name => $byType) {
			foreach ($byType as $target => $titles) {
				if (! $this->permissions->can($account, ContentAction::Create, $target) || ! $this->permissions->can($account, ContentAction::Publish, $target)) {
					return self::error(
						sprintf('You aren\'t allowed to add new %s: %s.', $this->types->find($target)->labels->items ?? $target, implode(', ', $titles)),
						Status::Forbidden,
						$this->relations->find($type->name, $name)->field ?? $name
					);
				}
			}
		}

		if (! self::isDraft($changes, $entry)) {
			$problems = $this->limits->check($type->name, $entry?->id, $language, $after);

			if ($problems !== []) {
				$relation = $this->relations->byKey($problems[0]->key);
				$full     = $relation !== null && $problems[0]->kind === ProblemKind::InverseLimit ? $this->full($relation, $problems[0]->value, $entry->id ?? '') : null;

				return self::json([
					'error' => implode(' ', array_map(static fn (RelationProblem $problem): string => $problem->message, $problems)),
					...($relation === null ? [] : ['field' => $relation->field]),
					...($full === null ? [] : ['full' => $full])
				], Status::UnprocessableContent);
			}
		}

		$created = $found === [] ? null : $this->typed->create($found);

		return $created === null || $created->failed === [] ? null : self::error(
			sprintf('%s couldn\'t be written: %s', implode(', ', array_keys($created->failed)), implode(' ', $created->failed)),
			Status::UnprocessableContent
		);
	}

	/**
	 * Describes a target a save named that its inverse's `max` already
	 * has enough entries naming (D-608), for the editor to name it and
	 * offer a way out: its `id`, `title`, and `type`, the `max`, and the
	 * first other published target by title with room (`room`: `title`,
	 * `slug`, and how many more it takes, `left`), or `null` for none.
	 *
	 * @return ?array{id: ?string, title: string, type: string, max: int, room: ?array{title: string, slug: string, left: int}}
	 */
	private function full(Relation $relation, string $slug, string $source): ?array
	{
		$max    = $relation->inverse === false ? null : $relation->inverse->max;
		$type   = $relation->to[0] ?? null;
		$target = $type === null ? null : $this->content->named($type, $slug);

		if ($max === null || $type === null || $target === null) {
			return null;
		}

		$others = array_values(array_filter(
			$this->content->query()->type($type)->withLanding(false)->orderBy('title', Order::Asc)->limit(null)->get()->all(),
			static fn (Entry $other): bool => $other->id !== null && $other->id !== $target->id
		));
		$taken  = $this->limits->taken($relation, array_map(static fn (Entry $other): string => (string) $other->id, $others), $source);
		$room   = array_find($others, static fn (Entry $other): bool => ($taken[(string) $other->id] ?? 0) < $max);

		return [
			'id'    => $target->id,
			'title' => $target->title,
			'type'  => $type,
			'max'   => $max,
			'room'  => $room === null ? null : ['title' => $room->title, 'slug' => $room->key, 'left' => $max - ($taken[(string) $room->id] ?? 0)]
		];
	}

	/**
	 * Says which of an entry's relations are out of their limits, which
	 * keeps it from being published (D-596), or `null` when none are.
	 */
	private function outOfLimits(Entry $entry): ?string
	{
		$problems = $this->limits->check($entry->type->name, $entry->id, $entry->language, $this->content->editable($entry)->frontMatter);

		return $problems === [] ? null : implode(' ', array_map(static fn (RelationProblem $problem): string => $problem->message, $problems));
	}

	/**
	 * Adds a published entry's address to its `redirect_from`, after the
	 * old addresses (the changes' own, if they set them).
	 */
	private function withRedirect(EntryChanges $changes, Entry $entry, EditableEntry $file, bool $redirect): EntryChanges
	{
		$from = $entry->isPublished() && $redirect ? $this->urls->entry($entry) : null;

		if ($from === null) {
			return $changes;
		}

		$keys = $this->keys($entry->type->name, 'redirect_from');
		$key  = array_find($keys, static fn (string $key): bool => array_key_exists($key, $changes->set))
			?? array_find($keys, static fn (string $key): bool => array_key_exists($key, $file->frontMatter));
		$old  = $key === null ? [] : ($changes->set[$key] ?? $file->frontMatter[$key] ?? []);
		$list = array_values(array_filter(is_array($old) ? $old : [$old], is_string(...)));

		if (in_array($from, $list, true)) {
			return $changes;
		}

		$set = $changes->set;
		unset($set[$key ?? 'redirect_from']);

		return new EntryChanges([...$set, ($key ?? 'redirect_from') => [...$list, $from]], $changes->remove, $changes->body);
	}

	/**
	 * Returns what's wrong with renaming an entry to a slug, or `null`,
	 * in the folder of keys it's in, or moving to (`.` for the top).
	 */
	private function slugProblem(Entry $entry, string $slug, ?string $folder = null): ?string
	{
		if ($entry->landing) {
			return sprintf('"%s" is its folder\'s landing page, so its slug is the folder\'s.', $entry->title);
		}

		if (ErrorPage::status($entry) !== null && $slug !== $entry->slug) {
			return sprintf('"%s" is the page for error %s, so its slug is the status.', $entry->title, $entry->slug);
		}

		if (! Slug::isSlug($slug)) {
			return sprintf('Slugs are lowercase letters, numbers, and hyphens; try "%s".', Slug::from($slug) ?: 'untitled');
		}

		$folder ??= dirname($entry->key);
		$key      = ($folder === '.' ? '' : "{$folder}/") . $slug;

		return match ($this->content->named($entry->type->name, $key)?->status) {
			null              => null,
			EntryStatus::Trash => sprintf('A %s in the trash has the slug "%s"; restore it, or delete it permanently, to use it.', $entry->type->labels->item, $slug),
			default           => sprintf('Another %s already has the slug "%s".', $entry->type->labels->item, $slug)
		};
	}

	/**
	 * Returns why the account may not make a change, or `null`.
	 */
	private function refusal(Account $account, Entry $entry, EntryChanges $changes): ?string
	{
		if (! self::isDraft($changes, $entry) && ! $this->permissions->can($account, ContentAction::Publish, $entry)) {
			return 'You aren\'t allowed to publish that entry; keep it a draft.';
		}

		// The byline (its type's byline relation, D-602) is what makes an
		// entry yours.
		$field = $this->types->byline($entry->type->name)?->field;

		if ($field === null || $this->permissions->can($account, ContentAction::EditOthers, $entry)) {
			return null;
		}

		$keys    = $this->keys($entry->type->name, $field);
		$touched = array_any($keys, static fn (string $key): bool => array_key_exists($key, $changes->set) || in_array($key, $changes->remove, true));

		if (! $touched) {
			return null;
		}

		$value   = array_find_key($changes->set, static fn (mixed $value, string|int $key): bool => in_array($key, $keys, true));
		$authors = $value === null ? [] : (array) $changes->set[$value];

		return $account->author !== null && in_array($account->author, $authors, true)
			? null
			: 'You can add authors, but not take yourself off an entry.';
	}

	/**
	 * Applies the status shortcut.
	 *
	 * @param  array<mixed> $input
	 * @throws InvalidEdit
	 */
	private function withStatus(EntryChanges $changes, string $status, array $input, ?Entry $entry): EntryChanges
	{
		if (! in_array($status, self::STATUSES, true)) {
			throw new InvalidEdit(sprintf('"status" must be one of %s.', implode(', ', self::STATUSES)));
		}

		$set    = $changes->set;
		$remove = array_values(array_diff($changes->remove, ['status']));
		$now    = $this->clock->now();
		$index  = $entry !== null && IndexPage::is($entry);

		if ($status === 'scheduled' && $index) {
			throw new InvalidEdit('An index page can\'t be scheduled; publish it or keep it a draft.');
		}

		unset($set['status']);

		if ($status === 'draft') {
			$set['status'] = 'draft';
		} else {
			$remove[] = 'status';
		}

		if ($status === 'scheduled') {
			$when = self::date($input['published'] ?? null, $this->app);

			if ($when === null || $when <= $now) {
				throw new InvalidEdit('Scheduling needs a future "published" date.');
			}

			$set['published'] = self::dateString($when);
		}

		if ($status === 'published' && ! $index && ! isset($set['published']) && ($entry?->published === null || $entry->published > $now)) {
			$set['published'] = self::dateString($now->setTimezone($this->app->timezone()));
		}

		// A new entry has no file yet, so there's no status key to clear.
		if ($entry === null) {
			$remove = [];
		}

		return new EntryChanges($set, array_values(array_unique($remove)), $changes->body);
	}

	/**
	 * Whether an entry stays (or starts as) a draft after the changes.
	 */
	private static function isDraft(EntryChanges $changes, ?Entry $entry): bool
	{
		if (array_key_exists('status', $changes->set)) {
			return $changes->set['status'] === 'draft';
		}

		if (in_array('status', $changes->remove, true) || $entry === null) {
			return false;
		}

		return $entry->status === EntryStatus::Draft;
	}

	/**
	 * Returns the account's profile for a new entry's byline: the type's
	 * byline relation (D-602), when it has one.
	 *
	 * @return array<string, list<string>|string>
	 */
	private function authorDefault(Account $account, string $type): array
	{
		$byline = $this->types->byline($type);

		return $byline === null || $account->author === null
			? []
			: [$byline->field => $byline->multiple ? [$account->author] : $account->author];
	}

	/**
	 * Returns a field's keys under a type's schema: its name and aliases.
	 *
	 * @return list<string>
	 */
	private function keys(string $type, string $name): array
	{
		$field = $this->types->schema($type)->field($name);

		return $field === null ? [$name] : [$field->name, ...$field->aliases];
	}

	/**
	 * Returns whether the editor offers a field: any that isn't a
	 * classify relation's term field, and a term field when its relation
	 * names the type, or names none (every type) and the type isn't a term
	 * type itself, or the file uses it.
	 *
	 * @param array<mixed> $frontMatter
	 */
	private function groups(Field $field, ContentType $type, array $frontMatter): bool
	{
		foreach ($this->types->classifications() as $relation) {
			if ($relation->field !== $field->name) {
				continue;
			}

			foreach ($relation->keys() as $key) {
				if (array_key_exists($key, $frontMatter)) {
					return true;
				}
			}

			return $relation->from === []
				? ! $this->types->isTermType($type->name)
				: in_array($type->name, $relation->from, true);
		}

		return true;
	}

	/**
	 * Describes an entry for the editor.
	 *
	 * @return array<string, mixed>
	 */
	private function describe(Account $account, Entry $entry, EditableEntry $file): array
	{
		$index  = IndexPage::is($entry);
		$people = $this->archivePages->isList($entry);
		$person = $this->archivePages->isTarget($entry);
		$error  = ErrorPage::status($entry);
		$home   = $this->homepage->describe($entry);
		$fields = $this->types->schema($entry->type->name)->fields;
		$live   = $entry->status !== EntryStatus::Trash;

		// An index page describes the type's archive, not one of its
		// entries, so the type's fields don't apply; its title and status
		// do. With no date field, it can't be scheduled. A relation
		// archive's list page, and a page written for one target's archive
		// (D-602), introduce their pages the same way.
		if ($index || $people || $person) {
			$fields = array_filter($fields, static fn (Field $field): bool => in_array($field->name, ['title', 'status'], true));
		}

		// The root page (D-420) is the top of the tree, so it has no
		// siblings to be placed among.
		if (Homepage::isRootPage($entry)) {
			$fields = array_filter($fields, static fn (Field $field): bool => $field->name !== Position::FIELD);
		}

		// The schema has the term field of every classify relation from the
		// type, but one from every type isn't offered on a term type itself
		// (D-283, D-593). One the file
		// already uses stays, so it can still be edited. `trashed` is the
		// trash's (D-484), never edited, and `translation_of` links a
		// translation (D-511), kept as it is.
		$fields = array_filter($fields, fn (Field $field): bool => $this->groups($field, $entry->type, $file->frontMatter) && ! in_array($field->name, [EntryFields::TRASHED, EntryFields::TRANSLATION_OF], true));

		[$values, $extra] = self::split($fields, $file->frontMatter);

		// The body starts at its first line, without the blank lines after
		// the front matter; a save keeps those (D-334).

		return [
			'path'        => $entry->path,
			'id'          => $entry->id,
			'handle'      => $this->handles->of($entry),
			'slug'        => $entry->slug,
			'key'         => $entry->key,
			'parent'      => $entry->type instanceof Tree && ! $entry->landing ? ($entry->type->parentKey($entry->key, []) ?? '') : null,
			'revision'    => $file->version,
			'modified'    => $file->modified === null ? null : new DateTimeImmutable('@' . $file->modified)->format(DateTimeInterface::ATOM),
			'title'       => $entry->title,
			'status'      => $entry->status->value,
			'trashed'     => self::trashed($entry),
			'own'         => $this->permissions->owns($account, $entry),
			'url'         => $entry->isPublished() ? $this->urls->entry($entry) : null,
			'type'        => $this->typeOf($entry->type, $fields),
			'inherited'   => (object) $this->inherited($entry, $file),
			'linkedFrom'  => $this->linkedFrom($entry),
			'index'       => $index,
			'archivePage' => $people,
			'archive'     => $this->archiveOf($entry),
			'introduces'  => $this->introduces($entry, $index),
			'errorPage'   => ErrorPage::status($entry),
			...$home,
			'values'      => $values,
			'extra'       => $extra,
			'body'        => substr($file->body, strlen(DocumentEditor::gap($file->body))),
			'can'         => [
				'edit'         => $live && $this->permissions->can($account, ContentAction::Edit, $entry),
				'publish'      => $live && $this->permissions->can($account, ContentAction::Publish, $entry),
				'rename'       => $live && ! $entry->landing && ! $people && ! $person && $error === null && ! $this->isLinked($entry),
				'move'         => $live && $entry->type instanceof Tree && ! $entry->landing && $error === null,
				'delete'       => ! $index && ! $person && $this->permissions->can($account, ContentAction::Delete, $entry),
				'duplicate'    => $live && ! $entry->landing && ! $people && ! $person && $error === null && $this->permissions->can($account, ContentAction::Create, $entry->type->name),
				'makeHomepage' => $live && $home['homeInstead'] !== null && $this->permissions->can($account, Capability::SiteSettings->value)
			],
			'violations'  => array_map(static fn (Violation $violation): array => [
				'field'    => $violation->field,
				'message'  => $violation->message,
				'severity' => $violation->severity->value
			], $this->linter->lintFile($entry->path))
		];
	}

	/**
	 * Whether an entry is a profile an account is linked to, by its slug
	 * (D-355), which then can't change.
	 */
	private function isLinked(Entry $entry): bool
	{
		if (! $entry->type instanceof Profiles) {
			return false;
		}

		try {
			return array_any($this->accounts->all(), static fn (Account $account): bool => $account->author === $entry->key);
		} catch (AuthException) {
			return true;
		}
	}

	/**
	 * Describes the relation archive page an entry is (D-602, once people
	 * fields' D-353), or `null`: the relation's `relation` name and plural
	 * `label`, and for a page written for one target's archive, the
	 * target's `target` slug, `targetTitle`, `targetId`, and `targetType`
	 * (all `null` for a list page).
	 *
	 * @return ?array{relation: string, label: string, target: ?string, targetTitle: ?string, targetId: ?string, targetType: ?string}
	 */
	private function archiveOf(Entry $entry): ?array
	{
		$label = self::relationLabel(...);
		$list  = $this->archivePages->relationOf($entry);

		if ($list !== null) {
			return ['relation' => $list->name, 'label' => $label($list), 'target' => null, 'targetTitle' => null, 'targetId' => null, 'targetType' => null];
		}

		$relation = $this->archivePages->targetRelationOf($entry);

		if ($relation === null) {
			return null;
		}

		$slug   = basename($entry->key);
		$target = $this->content->named($relation->to[0], $slug);

		return ['relation' => $relation->name, 'label' => $label($relation), 'target' => $slug, 'targetTitle' => $target->title ?? $slug, 'targetId' => $target?->id, 'targetType' => $relation->to[0]];
	}

	/**
	 * Describes a type for the editor: its name, kind, whether it's
	 * dated, the fields it edits (as forms take them), and the field sets
	 * attached to it (D-337), each with its `name`, `label`,
	 * `description`, `slot` (the one it's in, D-347: the slot it names, or
	 * its kind's default), and the names of its `fields`, so the editor
	 * groups them where the slot puts them.
	 *
	 * @param  array<array-key, Field> $fields
	 * @return array<string, mixed>
	 */
	private function typeOf(ContentType $type, array $fields): array
	{
		return [
			'name'   => $type->name,
			'kind'   => $type->kind()->value,
			'terms'  => $this->types->classification($type->name) !== null,
			'hierarchical' => $this->types->nestsByParent($type->name),
			'byline' => $this->types->byline($type->name)?->field,
			'dated'  => $type->dateArchives !== DateArchives::None,
			'fields' => array_values(array_map(fn (Field $field): array => [...$field->toForm(), ...$this->relationOf($type, $field)], $fields)),
			'sets'   => array_map(fn (FieldSet $set): array => [
				'name'        => $set->name,
				'label'       => $set->label,
				'description' => $set->description,
				'slot'        => $this->targets->slotFor($set)->name ?? 'details',
				'fields'      => array_keys($set->schema->fields)
			], $this->types->setsFor($type->name))
		];
	}

	/**
	 * Returns what the editor's picker follows of the relation a field
	 * holds (D-599), as `relation`: its `name` and `key` (`recipe.cooks`,
	 * which its picker's suggestions ask by, D-607), whether it's `ordered`, its
	 * `min` and `max`, and whether targets are created as they're typed
	 * (`create`); none for a field that isn't one.
	 *
	 * The picker draws it with the relation's `control` (D-599).
	 *
	 * @return array{relation?: array{name: string, key: string, label: string, ordered: bool, min: int, max: ?int, create: bool, control: string}}
	 */
	private function relationOf(ContentType $type, Field $field): array
	{
		$relation = array_find(
			$this->relations->for($type->name),
			static fn (Relation $relation): bool => ! $relation->kind->isWithinType() && $relation->field === $field->name
		);

		return $relation === null ? [] : ['relation' => [
			'name'    => $relation->name,
			'key'     => $relation->key($type->name),
			'label'   => $relation->label,
			'ordered' => $relation->ordered,
			'min'     => $relation->min,
			'max'     => $relation->max ?? ($relation->multiple ? null : 1),
			'create'  => $relation->create,
			'control' => $relation->control($this->types->nestsByParent($relation->to[0]))->value
		]];
	}

	/**
	 * Returns what links to an entry, by relation, for the editor's Linked
	 * From (D-599, D-608): each group's `key` (`movie.actors`), what the
	 * target's side is called (`label`, the inverse's, else `''`), the
	 * source type's plural (`type`) and name (`typeName`), the relation's
	 * label (`relation`), how many link (`count`) and how many of those
	 * are drafts (`drafts`), and its `entries` (`id`, `title`, `status`,
	 * `type`, its singular label, `date`, `Y-m-d` or `null`, and `image`):
	 * all of them up to 50, else the first 8. The trash is never listed,
	 * and groups go most linked first.
	 *
	 * @return list<array{key: string, label: string, type: string, typeName: string, relation: string, count: int, drafts: int, entries: list<array<string, mixed>>}>
	 */
	private function linkedFrom(Entry $entry): array
	{
		$groups = [];

		foreach ($this->referrers->grouped($entry) as $group) {
			$entries = array_values(array_filter($group['entries'], static fn (Entry $source): bool => $source->status !== EntryStatus::Trash));
			$count   = count($entries);

			if ($count === 0) {
				continue;
			}

			$groups[] = [
				'key'      => $group['key'],
				'label'    => $group['relation']->inverse === false ? '' : $group['relation']->inverse->label,
				'type'     => $this->types->find($group['type'])->labels->plural ?? $group['type'],
				'typeName' => $group['type'],
				'relation' => self::relationLabel($group['relation']),
				'count'    => $count,
				'drafts'   => count(array_filter($entries, static fn (Entry $source): bool => ! $source->isPublished())),
				'entries'  => array_map(static fn (Entry $source): array => [
					'id'     => $source->id,
					'title'  => $source->title,
					'status' => $source->status->value,
					'type'   => $source->type->labels->singular,
					'date'   => $source->published?->format('Y-m-d'),
					'image'  => is_string($image = $source->field('image')) && $image !== '' ? $image : null
				], array_slice($entries, 0, $count <= self::LISTED_WHOLE ? self::LISTED_WHOLE : self::LISTED))
			];
		}

		usort($groups, static fn (array $a, array $b): int => [$b['count'], $a['key']] <=> [$a['count'], $b['key']]);

		return $groups;
	}

	/**
	 * Returns what a relation is called: its label, else its name.
	 */
	private static function relationLabel(Relation $relation): string
	{
		return $relation->label === '' ? ucfirst(str_replace('_', ' ', $relation->name)) : $relation->label;
	}

	/**
	 * Describes the archive an index page (D-274) or a relation archive's
	 * page (D-602) introduces, for the editor's Archive Page row (D-608),
	 * or `null` for another entry: the archive's address (`url`, `null`
	 * when it has none), how many published entries it lists (`listed`),
	 * what they are (`item` and `items`, mid-sentence), and how many
	 * drafts and scheduled entries it leaves out (`drafts`).
	 *
	 * @return ?array{url: ?string, listed: int, item: string, items: string, drafts: int}
	 */
	private function introduces(Entry $entry, bool $index): ?array
	{
		$type    = $entry->type;
		$waiting = [EntryStatus::Draft, EntryStatus::Scheduled];

		if ($index) {
			$query = $this->content->query()->type($type->name)->withLanding(false)
				->exceptNames(...$this->archivePages->listPages($type))
				->exceptIn(...$this->archivePages->targetFolders($type));

			return [
				'url'    => $this->urls->collection($type),
				'listed' => $query->count(),
				'item'   => $type->labels->item,
				'items'  => $type->labels->items,
				'drafts' => $query->any()->withLanding(false)->status(...$waiting)->count()
			];
		}

		$list = $this->archivePages->relationOf($entry);

		if ($list !== null) {
			return [
				'url'    => $this->urls->relatedList($type, $list),
				'listed' => count($this->relationArchives->linked($type, $list)),
				'item'   => $this->types->find($list->to[0] ?? '')->labels->item ?? 'entry',
				'items'  => $this->types->find($list->to[0] ?? '')->labels->items ?? 'entries',
				'drafts' => 0
			];
		}

		$relation = $this->archivePages->targetRelationOf($entry);
		$term     = $relation?->termKey();

		if ($relation === null || $term === null) {
			return null;
		}

		$slug  = basename($entry->key);
		$query = $this->content->query()->type($type->name)->whereTerm($term, $slug);

		return [
			'url'    => $this->urls->related($type, $relation, $slug),
			'listed' => $query->count(),
			'item'   => $type->labels->item,
			'items'  => $type->labels->items,
			'drafts' => $query->any()->withLanding(false)->status(...$waiting)->count()
		];
	}

	/**
	 * Returns, for a translation (D-511), what each of its relations uses
	 * from its original by the relation's `translations` rule (D-587,
	 * D-599), by field: the `rule` (`fallback`: the original's when it has
	 * none of its own; `add`: the original's and its own) and the
	 * original's `values`, `title`, and `language` (its name, for
	 * "from English", D-607). A relation whose rule is `own`, or whose
	 * original has none, isn't listed.
	 *
	 * @return array<string, array{rule: string, values: list<string>, title: string, language: string}>
	 */
	private function inherited(Entry $entry, EditableEntry $file): array
	{
		$of       = $file->frontMatter[EntryFields::TRANSLATION_OF] ?? null;
		$original = is_string($of) ? $this->content->find($of) : null;

		if ($original === null || $original->id === $entry->id) {
			return [];
		}

		$inherited = [];

		foreach ($this->relations->for($entry->type->name) as $relation) {
			if ($relation->kind->isWithinType() || $relation->translations === TranslationRule::Own) {
				continue;
			}

			$values = LinkResolver::values($original->field($relation->field));

			if ($values !== []) {
				$inherited[$relation->field] = [
					'rule'     => $relation->translations->value,
					'values'   => $values,
					'title'    => $original->title,
					'language' => $this->app->languages->find($original->language)?->name() ?? $original->language
				];
			}
		}

		return $inherited;
	}

	/**
	 * Splits front matter into field values by name (from the first of a
	 * field's name and aliases the file uses) and everything else but the
	 * id (D-477), which is answered on its own.
	 *
	 * @param  array<array-key, Field> $fields
	 * @param  array<array-key, mixed> $frontMatter
	 * @return array{array<string, mixed>, array<string, mixed>}
	 */
	private static function split(array $fields, array $frontMatter): array
	{
		$values  = [];
		$claimed = [EntryFields::ID, EntryFields::TRASHED, EntryFields::TRANSLATION_OF];

		foreach ($fields as $field) {
			foreach ([$field->name, ...$field->aliases] as $key) {
				if (array_key_exists($key, $frontMatter)) {
					$values[$field->name] ??= $frontMatter[$key];
					$claimed[]              = $key;
				}
			}
		}

		$extra = [];

		foreach ($frontMatter as $key => $value) {
			if (! in_array((string) $key, $claimed, true)) {
				$extra[(string) $key] = $value;
			}
		}

		return [$values, $extra];
	}

	/**
	 * Returns the request's account (the `Authenticate` middleware sets it).
	 */
	private static function account(ServerRequestInterface $request): Account
	{
		$account = $request->getAttribute(Account::class);

		return $account instanceof Account ? $account : throw new LogicException('The editing API needs the Authenticate middleware.');
	}

	/**
	 * Returns the request's JSON object, or `[]`.
	 *
	 * @return array<mixed>
	 */
	private static function input(ServerRequestInterface $request): array
	{
		try {
			$input = json_decode((string) $request->getBody(), true, 32, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			return [];
		}

		return is_array($input) ? $input : [];
	}

	/**
	 * Returns an input map (`set`).
	 *
	 * @param  array<mixed>         $input
	 * @return array<string, mixed>
	 * @throws InvalidEdit
	 */
	private static function map(array $input, string $key): array
	{
		$value = $input[$key] ?? [];

		if (! is_array($value) || ($value !== [] && array_is_list($value))) {
			throw new InvalidEdit(sprintf('"%s" must be an object of field names to values.', $key));
		}

		$map = [];

		foreach ($value as $name => $item) {
			$map[(string) $name] = $item;
		}

		return $map;
	}

	/**
	 * Returns an input list of names (`remove`).
	 *
	 * @param  array<mixed>  $input
	 * @return list<string>
	 * @throws InvalidEdit
	 */
	private static function list(array $input, string $key): array
	{
		$value = $input[$key] ?? [];

		if (! is_array($value) || ! array_is_list($value) || ! array_all($value, static fn (mixed $item): bool => is_string($item))) {
			throw new InvalidEdit(sprintf('"%s" must be a list of field names.', $key));
		}

		/** @var list<string> $value */
		return $value;
	}

	/**
	 * Returns an input string, or a default.
	 *
	 * @param  array<mixed> $input
	 * @throws InvalidEdit
	 */
	private static function stringOr(array $input, string $key, string $default): string
	{
		$value = $input[$key] ?? $default;

		return is_string($value) ? $value : throw new InvalidEdit(sprintf('"%s" must be text.', $key));
	}

	/**
	 * Reads a date from input, in the site's timezone unless it says
	 * otherwise, or returns `null`.
	 */
	private static function date(mixed $value, AppConfig $app): ?DateTimeImmutable
	{
		if (! is_string($value) || trim($value) === '') {
			return null;
		}

		try {
			return new DateTimeImmutable($value, $app->timezone());
		} catch (Exception) {
			return null;
		}
	}

	/**
	 * Writes a date the way people do in front matter.
	 */
	private static function dateString(DateTimeImmutable $date): string
	{
		return $date->format('Y-m-d H:i:s P');
	}

	/**
	 * Builds an uncached JSON response.
	 *
	 * @param array<string, mixed> $data
	 */
	private static function json(array $data, Status $status = Status::Ok): ResponseInterface
	{
		return Response::json($data, $status, ['Cache-Control' => 'no-store']);
	}

	/**
	 * Builds an error.
	 */
	private static function error(string $message, Status $status, ?string $field = null): ResponseInterface
	{
		return self::json(['error' => $message, ...($field === null ? [] : ['field' => $field])], $status);
	}
}
