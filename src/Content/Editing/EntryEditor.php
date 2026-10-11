<?php

/**
 * Entry editor.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Editing;

use DateTimeImmutable;
use Exception;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Blush\Activity\ActivityAction;
use Blush\Activity\ActivityException;
use Blush\Activity\ActivityLog;
use Blush\Activity\Activity;
use Blush\Activity\Actor;
use Blush\Auth\Account;
use Blush\Auth\AccountProfiles;
use Blush\Auth\ContentAction;
use Blush\Auth\Permissions;
use Blush\Content\Entries;
use Blush\Content\Entry\Entry;
use Blush\Content\Relation\ProblemKind;
use Blush\Content\Relation\Relation;
use Blush\Content\Relation\RelationLimits;
use Blush\Content\Relation\RelationProblem;
use Blush\Content\Relation\Relations;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Routing\RedirectOrigin;
use Blush\Content\Routing\RedirectWriter;
use Blush\Content\Status as EntryStatus;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\Tree;
use Blush\Content\TypedTargets;
use Blush\Content\Writer\DocumentEditor;
use Blush\Content\Writer\EditableEntry;
use Blush\Content\Writer\EntryChanges;
use Blush\Content\Writer\WriteConflict;
use Blush\Content\Writer\WriteException;
use Blush\Core\AppConfig;
use Blush\Field\Field;
use Blush\Routing\InvalidRoute;
use Blush\Storage\Record\Order;
use Blush\Storage\Record\RecordException;
use Blush\Support\Slug;

/**
 * The one write path for entries (D-756): every rule a save follows, for
 * the admin's editing API, the MCP server's tools, and later the API's
 * writes, so nothing saves what the admin would refuse. Each write is
 * recorded in the activity log (D-758) by who made it and how (`Actor`),
 * with what it replaced, for Undo.
 *
 * **Permissions come from the change, not only the entry,** and are the
 * entry's type's (D-359, `content.{type}.edit` and so on): editing needs
 * to edit the entry (with D-219's ownership and live-entry rules); a
 * result that isn't a draft needs to publish it; changing the authors so
 * the account's own author isn't among them needs to edit anyone's;
 * creating needs to create entries of the type; trashing needs to delete
 * the entry. Raw HTML a body adds is checked by capability (D-495).
 *
 * **Relations are checked before a write** (D-596): a value a save adds
 * in a relation that creates its targets (a tag), naming no entry, is
 * written as one, published and titled as typed, first, so it's linked
 * by id; when the account may not create and publish entries of that
 * type, the save is refused with the relation's `field`. A save that
 * leaves the entry live (as publishing does) with a relation below its
 * `min`, above its `max`, or naming a target already named by its
 * inverse's `max` is refused with the relation's `field` (and, for that
 * last, `full`: the target, and another with room, D-608).
 *
 * `status` is a shortcut: `draft` sets `status: draft`; `published`
 * clears it and dates the entry now if it has no date or a future one;
 * `scheduled` clears it and sets `published` (a future date, required).
 *
 * **A type's index page** (`IndexPage`, D-274) is edited without the
 * type's fields: only its title and status are. It's never scheduled or
 * dated by publishing, and never moved to the trash.
 *
 * Every write throws `EditRefused` when it's refused, saying why.
 */
final readonly class EntryEditor
{
	/**
	 * The statuses the shortcut takes.
	 */
	public const array STATUSES = ['draft', 'published', 'scheduled'];

	public function __construct(
		private Entries $content,
		private ContentTypes $types,
		private ContentUrls $urls,
		private Permissions $permissions,
		private AppConfig $app,
		private ClockInterface $clock,
		private AccountProfiles $profiles,
		private HtmlGuard $html,
		private Relations $relations,
		private RelationLimits $limits,
		private TypedTargets $typed,
		private ArchivePages $archivePages,
		private RedirectWriter $redirects,
		private ActivityLog $activity,
		private LoggerInterface $logger
	) {}

	/**
	 * Returns a copy whose permissions are narrowed to a token's
	 * capabilities (`null` for none), as the MCP server's and the API's
	 * writes are (D-744).
	 *
	 * @param  ?list<string> $capabilities
	 * @throws \Blush\Auth\AuthException For a name that isn't a capability's.
	 */
	public function limitedTo(?array $capabilities): self
	{
		$permissions = $this->permissions->limitedTo($capabilities);

		return clone($this, ['permissions' => $permissions, 'html' => $this->html->withPermissions($permissions)]);
	}

	/**
	 * Creates an entry. New entries are drafts unless asked otherwise, and
	 * credit the account's author. A tree's page may name a `parent`
	 * (another of its pages, by key) to go under (D-408).
	 *
	 * @throws EditRefused
	 */
	public function create(Actor $actor, NewEntry $new): Entry
	{
		$account = $actor->account;
		$type    = $this->types->find($new->type);

		if (! $this->permissions->can($account, ContentAction::Create, $type?->name)) {
			throw EditRefused::forbidden($type === null ? 'You aren\'t allowed to create entries.' : sprintf('You aren\'t allowed to create %s.', $type->labels->items));
		}

		if ($type === null || trim($new->title) === '') {
			throw EditRefused::invalid('Send a known "type" and a "title".');
		}

		$slug = $new->slug !== null && $new->slug !== '' ? $new->slug : Slug::from($new->title);
		$now  = $this->clock->now()->setTimezone($this->app->timezone());

		try {
			$set = [
				'title' => $new->title,
				'published' => self::dateString($now),
				...$this->authorDefault($account, $type->name),
				...$new->set
			];

			// The editor's body starts at its first line (D-334); the
			// file has a blank line before it.
			$changes = $this->withStatus(new EntryChanges(set: $set, body: DocumentEditor::gap($new->body) === '' ? "\n{$new->body}" : $new->body), $new->status, $new->published, null);
		} catch (WriteException $e) {
			throw EditRefused::unprocessable($e->getMessage(), previous: $e);
		}

		if (! self::isDraft($changes, null) && ! $this->permissions->can($account, ContentAction::Publish, $type->name)) {
			throw EditRefused::forbidden('You aren\'t allowed to publish, so new entries must be drafts.');
		}

		$html = $this->html->refusal($account, '', $changes->body ?? '');

		if ($html !== null) {
			throw EditRefused::forbidden($html, 'body');
		}

		$parent = null;

		if ($new->parent !== null && $new->parent !== '') {
			if (! $type instanceof Tree) {
				throw EditRefused::invalid(sprintf('Only pages of a tree go under another; %s don\'t.', $type->labels->items));
			}

			$parent = $this->content->named($type->name, $new->parent);

			if ($parent === null || $parent->landing) {
				throw EditRefused::unprocessable(sprintf('There\'s no "%s" in %s to put it under.', $new->parent, $type->labels->items), 'parent');
			}
		}

		// Written in the site's language, then moved to another (D-728).
		$language = $new->language ?? $this->app->languages->default->code;
		$problem  = $this->app->languages->isDefault($language) ? null : $this->languageProblem($type, $parent === null ? $slug : "{$parent->key}/{$slug}", $language);

		if ($problem !== null) {
			throw EditRefused::unprocessable($problem, 'language');
		}

		$this->related($account, $type, null, [], $changes);

		try {
			$entry = $this->content->create($type, $slug, $changes, $parent, $now);
			$entry = $this->app->languages->isDefault($language) ? $entry : $this->content->changeLanguage($entry, $language);
		} catch (WriteException $e) {
			// A trashed entry keeps its address until it's deleted (D-484).
			$holder = $this->content->named($type->name, $parent === null ? $slug : "{$parent->key}/{$slug}");

			throw EditRefused::unprocessable($holder?->status === EntryStatus::Trash
				? sprintf('“%s” in the trash has this address; restore it, or delete it permanently, first.', self::titleOf($holder))
				: $e->getMessage(), previous: $e);
		}

		$this->log($actor, ActivityAction::EntryCreate, $entry, sprintf('Created “%s”', self::titleOf($entry)));

		return $entry;
	}

	/**
	 * Changes an entry, at the revision that was read. A new `slug`
	 * renames it (D-277), first, so a slug that's refused changes nothing.
	 * With `redirect`, a published entry's old address redirects to it
	 * (D-680, D-686). A tree's page takes `parent` to move under it
	 * (D-410), with the pages under it, which get redirects too. A new
	 * publish date may rename a file its type's pattern names by date
	 * (D-519), the storage driver's to do.
	 *
	 * @throws EditRefused
	 */
	public function update(Actor $actor, Entry $entry, EntryEdit $edit, ?string $revision): Entry
	{
		$account = $actor->account;

		if (! $this->permissions->can($account, ContentAction::Edit, $entry)) {
			throw EditRefused::forbidden('You aren\'t allowed to edit that entry.');
		}

		if ($entry->status === EntryStatus::Trash) {
			throw EditRefused::unprocessable(self::inTrash($entry));
		}

		if ($revision === null) {
			throw EditRefused::revisionRequired('Send the "revision" you loaded, so no one else\'s change is lost.');
		}

		try {
			$changes = $this->stamped(new EntryChanges(set: $edit->set, remove: $edit->remove, body: $edit->body));
			$changes = $edit->status === null ? $changes : $this->withStatus($changes, $edit->status, $edit->published, $entry);
		} catch (WriteException $e) {
			throw EditRefused::unprocessable($e->getMessage(), previous: $e);
		}

		$refusal = $this->refusal($account, $entry, $changes);

		if ($refusal !== null) {
			throw EditRefused::forbidden($refusal);
		}

		$file = $this->editable($entry);

		// Only the HTML the body didn't have counts (D-495).
		if ($changes->body !== null) {
			$html = $this->html->refusal($account, $file->body, $changes->body);

			if ($html !== null) {
				throw EditRefused::forbidden($html, 'body');
			}
		}

		// A move (D-410): where to, by the new parent's key (`''` for the
		// top), or `false` for staying put.
		$move = false;

		if ($edit->parent !== null) {
			if (! $entry->type instanceof Tree || $entry->landing || ErrorPage::status($entry) !== null) {
				throw EditRefused::invalid(sprintf('Only a tree\'s pages move under another; "%s" doesn\'t.', $entry->title));
			}

			$target = $edit->parent === '' ? null : $this->content->named($entry->type->name, $edit->parent);

			if ($edit->parent !== '' && ($target === null || $target->landing)) {
				throw EditRefused::unprocessable(sprintf('There\'s no "%s" in %s to move it under.', $edit->parent, $entry->type->labels->items), 'parent');
			}

			if ($entry->type->parentKey($entry->key, []) !== $target?->key) {
				$move = $target;
			}
		}

		$rename  = $edit->slug !== null && $edit->slug !== '' && $edit->slug !== $entry->slug ? $edit->slug : null;
		$folder  = $move === false ? dirname($entry->key) : ($move === null ? '.' : $move->key);
		$problem = $rename === null ? null : $this->slugProblem($entry, $rename, $folder);

		if ($problem !== null) {
			throw EditRefused::unprocessable($problem, 'slug');
		}

		if ($move !== false && $rename === null && $this->slugProblem($entry, $entry->slug, $folder) !== null) {
			throw EditRefused::unprocessable(sprintf('There\'s already a page at %s.', ($folder === '.' ? '' : "{$folder}/") . $entry->slug), 'parent');
		}

		// Another language (D-728), checked before anything is written.
		$language = $edit->language !== null && $edit->language !== $entry->language ? $edit->language : null;

		if ($language !== null) {
			$problem = $entry->landing || $this->archivePages->isList($entry) || $this->archivePages->isTarget($entry) || ErrorPage::status($entry) !== null
				? sprintf('"%s" keeps the language of the pages it goes with.', $entry->title)
				: $this->languageProblem($entry->type, ($folder === '.' ? '' : "{$folder}/") . ($rename ?? $entry->slug), $language, $entry);

			if ($problem !== null) {
				throw EditRefused::unprocessable($problem, 'language');
			}
		}

		$moved = $move !== false || $rename !== null;

		// The pages under a tree's page, whose addresses change with its.
		$under = $edit->redirect && $moved && $entry->type instanceof Tree
			? array_values(array_filter(
				$this->content->query()->any()->type($entry->type->name)->limit(null)->get()->all(),
				static fn (Entry $item): bool => str_starts_with($item->key, "{$entry->key}/")
			))
			: [];

		// The old address can redirect to the new one, once it's saved.
		$from = $edit->redirect && $moved && $entry->isPublished() ? $this->urls->entry($entry) : null;

		try {
			$this->related($account, $entry->type, $entry, $file->frontMatter, $changes);
		} catch (WriteException $e) {
			throw EditRefused::unprocessable($e->getMessage(), previous: $e);
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

			if ($language !== null) {
				$revision = $this->content->changeLanguage($entry, $language, $revision)->version ?? $revision;
			}

			$updated = $this->content->change($entry, $changes, $revision);
		} catch (WriteConflict $e) {
			throw EditRefused::conflict($e->getMessage(), $e);
		} catch (WriteException $e) {
			throw EditRefused::unprocessable($e->getMessage(), previous: $e);
		}

		$this->redirectMoved($account, $move !== false ? RedirectOrigin::Move : RedirectOrigin::Rename, $from, $entry, $updated, $under);

		// Undo puts back what was replaced, only where it was (D-758).
		$before = $moved || $language !== null ? null : self::before($entry, $file);
		$action = $edit->isStatusOnly() ? ActivityAction::EntryStatus : ActivityAction::EntryUpdate;

		$this->log($actor, $action, $updated, $this->updateSummary($entry, $updated, $edit, $file), $before);

		return $updated;
	}

	/**
	 * Replaces one exact passage of an entry's body, found exactly once,
	 * for a small edit without sending the whole body back (D-756).
	 *
	 * @throws EditRefused
	 */
	public function replaceInBody(Actor $actor, Entry $entry, string $find, string $replace, ?string $revision): Entry
	{
		if ($find === '') {
			throw EditRefused::invalid('Send the passage to "find"; it can\'t be empty.', 'find');
		}

		$body  = $this->editable($entry)->body;
		$count = substr_count($body, $find);

		if ($count !== 1) {
			throw EditRefused::unprocessable($count === 0
				? sprintf('The body of “%s” doesn\'t have that passage; read it again and copy the passage exactly.', self::titleOf($entry))
				: sprintf('The body of “%s” has that passage %d times; send more of the text around it so it\'s found once.', self::titleOf($entry), $count), 'find');
		}

		return $this->update($actor, $entry, new EntryEdit(body: str_replace($find, $replace, $body)), $revision);
	}

	/**
	 * Publishes, unpublishes, or schedules an entry, at its current
	 * revision unless one is given (a bulk change's, D-301, loses no
	 * one's writing). Publishing needs its required fields filled and its
	 * relations within their limits, and dates an undated entry.
	 *
	 * @throws EditRefused
	 */
	public function setStatus(Actor $actor, Entry $entry, string $status, ?string $revision = null, ?string $published = null): Entry
	{
		if ($entry->status === EntryStatus::Trash) {
			throw EditRefused::unprocessable(self::inTrash($entry));
		}

		if (! $this->permissions->can($actor->account, ContentAction::Edit, $entry)) {
			throw EditRefused::forbidden('You aren\'t allowed to edit that entry.');
		}

		if ($status !== 'draft' && ! $this->permissions->can($actor->account, ContentAction::Publish, $entry)) {
			throw EditRefused::forbidden('You aren\'t allowed to publish that entry; keep it a draft.');
		}

		if ($status !== 'draft') {
			$missing = $this->missing($entry) ?? $this->outOfLimits($entry);

			if ($missing !== null) {
				throw EditRefused::unprocessable($missing);
			}
		}

		return $this->update($actor, $entry, new EntryEdit(status: $status, published: $published), $revision ?? $this->editable($entry)->version);
	}

	/**
	 * Moves an entry to the trash (D-484), and returns it with the status
	 * its file had (`null` for none), which an Undo puts back.
	 *
	 * @return array{Entry, ?string}
	 * @throws EditRefused
	 */
	public function trash(Actor $actor, Entry $entry, ?string $revision): array
	{
		if (! $this->permissions->can($actor->account, ContentAction::Delete, $entry)) {
			throw EditRefused::forbidden('You aren\'t allowed to delete that entry.');
		}

		if ($entry->status === EntryStatus::Trash) {
			throw EditRefused::unprocessable(sprintf('"%s" is already in the trash; delete it permanently, or restore it.', $entry->title));
		}

		if (IndexPage::is($entry)) {
			throw EditRefused::unprocessable(sprintf('"%s" is the index page for %s, so it can\'t be moved to the trash.', $entry->title, $entry->type->labels->items));
		}

		if ($revision === null) {
			throw EditRefused::revisionRequired('Send the "revision" you loaded, so no one else\'s change is lost.');
		}

		try {
			$file     = $this->content->editable($entry);
			$declared = $file->frontMatter['status'] ?? null;
			$trashed  = $this->content->trash($entry, $revision);
		} catch (WriteConflict $e) {
			throw EditRefused::conflict($e->getMessage(), $e);
		} catch (WriteException $e) {
			throw EditRefused::unprocessable($e->getMessage(), previous: $e);
		}

		$declared = is_string($declared) ? $declared : null;

		$this->log($actor, ActivityAction::EntryTrash, $trashed, sprintf('Moved “%s” to the trash', self::titleOf($entry)), ['status' => $declared]);

		return [$trashed, $declared];
	}

	/**
	 * Brings an entry back from the trash as a draft (D-237), or, for an
	 * Undo, with the status it had (`null` for none, so published), only
	 * at the revision moving it there wrote (D-525).
	 *
	 * @throws EditRefused
	 */
	public function restore(Actor $actor, Entry $entry, ?EntryStatus $status = EntryStatus::Draft, ?string $revision = null): Entry
	{
		if ($entry->status !== EntryStatus::Trash) {
			throw EditRefused::notFound('That isn\'t in the trash.');
		}

		if (! $this->permissions->can($actor->account, ContentAction::Delete, $entry)) {
			throw EditRefused::forbidden('You aren\'t allowed to restore that entry.');
		}

		if ($status === EntryStatus::Trash || $status === EntryStatus::Scheduled) {
			throw EditRefused::unprocessable('An entry is restored as "draft", "published", or `null` (no status, so published).');
		}

		if ($status !== EntryStatus::Draft && $revision === null) {
			throw EditRefused::revisionRequired('Send the "revision" moving it to the trash answered, so it\'s put back only as it was.');
		}

		if ($status !== EntryStatus::Draft && ! $this->permissions->can($actor->account, ContentAction::Publish, $entry)) {
			throw EditRefused::forbidden('You aren\'t allowed to publish that entry, so it can\'t be put back as it was; restore it as a draft.');
		}

		try {
			$restored = $this->content->restore($entry, $revision, $status);
		} catch (WriteConflict $e) {
			throw EditRefused::conflict(sprintf('"%s" changed in the trash since it was moved there, so it can\'t be put back as it was; restore it from the Trash.', $entry->title), $e);
		} catch (WriteException $e) {
			throw EditRefused::unprocessable($e->getMessage(), previous: $e);
		}

		$this->log($actor, ActivityAction::EntryRestore, $restored, sprintf('Restored “%s” from the trash', self::titleOf($entry)), ['trash' => true]);

		return $restored;
	}

	/**
	 * Copies an entry as a draft (D-275), titled "… (Copy)", slugged
	 * `{slug}-copy`, with the same authors, dated now. Needs to create
	 * entries of its type, and to edit the entry; a landing page can't be
	 * copied.
	 *
	 * @throws EditRefused
	 */
	public function duplicate(Actor $actor, Entry $entry): Entry
	{
		$account = $actor->account;

		if (! $this->permissions->can($account, ContentAction::Create, $entry->type->name) || ! $this->permissions->can($account, ContentAction::Edit, $entry)) {
			throw EditRefused::forbidden('You aren\'t allowed to duplicate that entry.');
		}

		if ($entry->status === EntryStatus::Trash) {
			throw EditRefused::unprocessable(self::inTrash($entry));
		}

		if (IndexPage::is($entry)) {
			throw EditRefused::unprocessable(sprintf('"%s" is the index page for %s, and there\'s only one.', $entry->title, $entry->type->labels->items));
		}

		if ($entry->landing) {
			throw EditRefused::unprocessable(sprintf('"%s" is the site\'s root page, and there\'s only one.', $entry->title));
		}

		$now   = $this->clock->now()->setTimezone($this->app->timezone());
		$title = sprintf('%s (Copy)', self::titleOf($entry));
		$set   = [
			'title'     => $title,
			'status'    => 'draft',
			'published' => self::dateString($now)
		];

		try {
			// The copy's name is its file's, and the original's old
			// addresses stay the original's.
			$copy = $this->content->duplicate($entry, Slug::from(($entry->slug === '' ? $title : $entry->slug) . '-copy'), new EntryChanges(set: $set, remove: ['slug']), $now);
		} catch (WriteException $e) {
			throw EditRefused::unprocessable($e->getMessage(), previous: $e);
		}

		$this->log($actor, ActivityAction::EntryCreate, $copy, sprintf('Copied “%s” as “%s”', self::titleOf($entry), $title));

		return $copy;
	}

	/**
	 * Undoes a change from the activity log (D-758): puts back what it
	 * replaced, only while the entry is as the change left it, and records
	 * that as a change of its own.
	 *
	 * @throws EditRefused
	 */
	public function undo(Actor $actor, Activity $activity): Entry
	{
		$entry = $this->content->find($activity->subject);

		if ($entry === null) {
			throw EditRefused::notFound(sprintf('“%s” is no longer there.', $activity->title));
		}

		if (! $activity->canUndo()) {
			throw EditRefused::unprocessable($activity->undone !== null ? 'That change was already undone.' : 'That change can\'t be undone.');
		}

		$before = $activity->before ?? [];
		$file   = $this->editable($entry);

		$moved = is_string($before['key'] ?? null) && $before['key'] !== $entry->key;

		if ($moved || ($activity->revision !== null && $file->version !== $activity->revision)) {
			throw EditRefused::conflict(sprintf('“%s” changed since; open it to edit.', self::titleOf($entry)));
		}

		$actor = $actor->undoing($activity);

		$undone = match ($activity->action) {
			ActivityAction::EntryTrash   => $this->restore($actor, $entry, self::statusFrom($before['status'] ?? null), $file->version),
			ActivityAction::EntryRestore => $this->trash($actor, $entry, $file->version)[0],
			default                      => $this->putBack($actor, $entry, $file, $before)
		};

		return $undone;
	}

	/**
	 * Returns the account's profile for a new entry's byline: the type's
	 * byline relation (D-602), when it has one and the profile is an
	 * entry, since a credit links only entries (D-584, D-663).
	 *
	 * @return array<string, list<string>|string>
	 */
	public function authorDefault(Account $account, string $type): array
	{
		$byline = $this->types->byline($type);
		$own    = $byline === null ? null : $this->profiles->slug($account);

		if ($byline === null || $own === null) {
			return [];
		}

		return [$byline->field => $byline->multiple ? [$own] : $own];
	}

	/**
	 * Puts back an entry's front matter and body as a change kept them,
	 * at the version it's at now.
	 *
	 * @param  array<string, mixed> $before
	 * @throws EditRefused
	 */
	private function putBack(Actor $actor, Entry $entry, EditableEntry $file, array $before): Entry
	{
		$frontMatter = is_array($before['frontMatter'] ?? null) ? $before['frontMatter'] : null;
		$body        = is_string($before['body'] ?? null) ? $before['body'] : null;

		if ($frontMatter === null || $body === null) {
			throw EditRefused::unprocessable('That change didn\'t keep what it replaced, so it can\'t be undone.');
		}

		$set = [];

		foreach ($frontMatter as $key => $value) {
			$set[(string) $key] = $value;
		}

		$remove = array_values(array_diff(array_map(strval(...), array_keys($file->frontMatter)), array_keys($set)));
		$status = $set['status'] ?? null;

		if ((! is_string($status) || $status !== 'draft') && ! $this->permissions->can($actor->account, ContentAction::Publish, $entry)) {
			throw EditRefused::forbidden('You aren\'t allowed to publish that entry, so this change can\'t be undone.');
		}

		if (! $this->permissions->can($actor->account, ContentAction::Edit, $entry)) {
			throw EditRefused::forbidden('You aren\'t allowed to edit that entry.');
		}

		try {
			$restored = $this->content->change($entry, new EntryChanges(set: $set, remove: $remove, body: $body), $file->version);
		} catch (WriteConflict $e) {
			throw EditRefused::conflict($e->getMessage(), $e);
		} catch (WriteException $e) {
			throw EditRefused::unprocessable($e->getMessage(), previous: $e);
		}

		$this->log($actor, ActivityAction::EntryUpdate, $restored, sprintf('Undid a change to “%s”', self::titleOf($restored)), self::before($entry, $file));

		return $restored;
	}

	/**
	 * Records a change in the activity log, with the version it wrote. A
	 * log that can't be written is logged; the change stands.
	 *
	 * @param ?array<string, mixed> $before
	 */
	private function log(Actor $actor, ActivityAction $action, Entry $entry, string $summary, ?array $before = null): void
	{
		if ($entry->id === null) {
			return;
		}

		try {
			$revision = $this->content->editable($entry)->version;
		} catch (WriteException) {
			$revision = null;
		}

		try {
			$this->activity->record($actor, $action, $entry->id, self::titleOf($entry), $summary, $before, $revision);
		} catch (ActivityException $e) {
			$this->logger->warning('A change to "{title}" couldn\'t be added to the activity log: {problem}', ['title' => $entry->title, 'problem' => $e->getMessage()]);
		}
	}

	/**
	 * Says what an update changed, for the activity log.
	 */
	private function updateSummary(Entry $was, Entry $now, EntryEdit $edit, EditableEntry $file): string
	{
		$title = self::titleOf($now);

		if ($edit->isStatusOnly()) {
			return match ($now->status) {
				EntryStatus::Published => sprintf('Published “%s”', $title),
				EntryStatus::Scheduled => sprintf('Scheduled “%s” for %s', $title, $now->published?->format('Y-m-d H:i') ?? 'later'),
				EntryStatus::Draft     => sprintf('Moved “%s” to draft', $title),
				default                => sprintf('Changed the status of “%s”', $title)
			};
		}

		$parts = [];

		if ($edit->body !== null && $edit->body !== $file->body) {
			$parts[] = 'the body';
		}

		$fields = array_values(array_diff([...array_keys($edit->set), ...$edit->remove], ['updated', 'status', 'published']));

		if ($fields !== []) {
			$parts[] = implode(', ', $fields);
		}

		if ($was->slug !== $now->slug || $was->key !== $now->key) {
			$parts[] = 'its address';
		}

		return $parts === [] ? sprintf('Edited “%s”', $title) : sprintf('Edited %s of “%s”', implode(', ', $parts), $title);
	}

	/**
	 * What a change keeps of an entry to put it back.
	 *
	 * @return array<string, mixed>
	 */
	private static function before(Entry $entry, EditableEntry $file): array
	{
		return ['key' => $entry->key, 'frontMatter' => $file->frontMatter, 'body' => $file->body];
	}

	/**
	 * Returns the entry as it's stored, for editing.
	 *
	 * @throws EditRefused
	 */
	private function editable(Entry $entry): EditableEntry
	{
		try {
			return $this->content->editable($entry);
		} catch (WriteException $e) {
			throw EditRefused::unprocessable($e->getMessage(), previous: $e);
		}
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
	 * as they're typed, before the entry is written.
	 *
	 * @param  array<array-key, mixed> $before The entry's front matter, none for a new one.
	 * @throws EditRefused When the account may not create and publish entries of a target's type, or a save that leaves the entry live leaves a relation out of its limits.
	 * @throws WriteException
	 */
	private function related(Account $account, ContentType $type, ?Entry $entry, array $before, EntryChanges $changes): void
	{
		$after    = array_diff_key([...$before, ...$changes->set], array_flip($changes->remove));
		$language = $entry->language ?? $this->app->languages->default->code;
		$found    = $this->typed->find($type->name, $language, $before, $after);

		foreach ($found as $name => $byType) {
			foreach ($byType as $target => $titles) {
				if (! $this->permissions->can($account, ContentAction::Create, $target) || ! $this->permissions->can($account, ContentAction::Publish, $target)) {
					throw EditRefused::forbidden(
						sprintf('You aren\'t allowed to add new %s: %s.', $this->types->find($target)->labels->items ?? $target, implode(', ', $titles)),
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

				throw EditRefused::unprocessable(
					implode(' ', array_map(static fn (RelationProblem $problem): string => $problem->message, $problems)),
					$relation?->field,
					$full === null ? [] : ['full' => $full]
				);
			}
		}

		$created = $found === [] ? null : $this->typed->create($found);

		if ($created !== null && $created->failed !== []) {
			throw EditRefused::unprocessable(sprintf('%s couldn\'t be written: %s', implode(', ', array_keys($created->failed)), implode(' ', $created->failed)));
		}
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
	 *
	 * @throws EditRefused
	 */
	private function outOfLimits(Entry $entry): ?string
	{
		$problems = $this->limits->check($entry->type->name, $entry->id, $entry->language, $this->editable($entry)->frontMatter);

		return $problems === [] ? null : implode(' ', array_map(static fn (RelationProblem $problem): string => $problem->message, $problems));
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
	 * Returns what's wrong with an entry of a type at a key being in a
	 * language (D-728), or `null`: a language the site doesn't have, one
	 * its translations already have, or a key that's taken there.
	 */
	private function languageProblem(ContentType $type, string $key, string $code, ?Entry $entry = null): ?string
	{
		$language = $this->app->languages->find($code);

		if ($language === null) {
			return sprintf('The site has no language "%s".', $code);
		}

		$translation = $entry === null ? null : $this->content->translations($entry)[$code] ?? null;

		if ($translation !== null && $translation->id !== $entry->id) {
			return sprintf('Its %s translation is “%s”.', $language->name(), self::titleOf($translation));
		}

		$found = $this->content->named($type->name, $key, $code);

		return $found === null || $found->id === $entry?->id ? null : sprintf('There\'s already a %s at %s in %s.', $type->labels->item, $key, $language->name());
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

		$own = $this->profiles->slug($account);

		return $own !== null && in_array($own, $authors, true)
			? null
			: 'You can add authors, but not take yourself off an entry.';
	}

	/**
	 * Applies the status shortcut.
	 *
	 * @throws EditRefused
	 * @throws WriteException
	 */
	private function withStatus(EntryChanges $changes, string $status, ?string $published, ?Entry $entry): EntryChanges
	{
		if (! in_array($status, self::STATUSES, true)) {
			throw EditRefused::invalid(sprintf('"status" must be one of %s.', implode(', ', self::STATUSES)), 'status');
		}

		$set    = $changes->set;
		$remove = array_values(array_diff($changes->remove, ['status']));
		$now    = $this->clock->now();
		$index  = $entry !== null && IndexPage::is($entry);

		if ($status === 'scheduled' && $index) {
			throw EditRefused::invalid('An index page can\'t be scheduled; publish it or keep it a draft.', 'status');
		}

		unset($set['status']);

		if ($status === 'draft') {
			$set['status'] = 'draft';
		} else {
			$remove[] = 'status';
		}

		if ($status === 'scheduled') {
			$when = self::date($published, $this->app);

			if ($when === null || $when <= $now) {
				throw EditRefused::invalid('Scheduling needs a future "published" date.', 'published');
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
	 * Redirects the old addresses of a published entry that moved or was
	 * renamed, and of the published pages under a tree's page that did
	 * (D-410), to the entries by id (D-680, D-686). The entry is saved by
	 * then, so a redirect that can't be written is logged, and the move
	 * stands.
	 *
	 * @param ?string     $from  The entry's old address, when it redirects.
	 * @param list<Entry> $under The pages that were under it, as they were.
	 */
	private function redirectMoved(Account $account, RedirectOrigin $via, ?string $from, Entry $was, Entry $now, array $under): void
	{
		$moves = [];
		$to    = $from === null ? null : $this->urls->entry($now);

		if ($from !== null && $to !== null) {
			$moves[] = ['from' => $from, 'to' => $to, 'entry' => $now->id, 'via' => $via];
		}

		foreach ($under as $page) {
			$found = $this->content->named($now->type->name, $now->key . substr($page->key, strlen($was->key)));
			$old   = $page->isPublished() ? $this->urls->entry($page) : null;
			$new   = $found === null ? null : $this->urls->entry($found);

			if ($found !== null && $old !== null && $new !== null) {
				$moves[] = ['from' => $old, 'to' => $new, 'entry' => $found->id, 'via' => RedirectOrigin::Move];
			}
		}

		if ($moves === []) {
			return;
		}

		try {
			$this->redirects->moved($moves, $account->id === '' ? null : $account->id);
		} catch (RecordException | InvalidRoute $error) {
			$this->logger->warning('The redirects for "{title}" couldn\'t be written: {problem}', ['title' => $now->title, 'problem' => $error->getMessage()]);
		}
	}

	/**
	 * Returns changes that also set `updated` to now (D-728, D-729): every
	 * change someone makes to an entry, in the editor or in bulk, says
	 * when it was made.
	 *
	 * @throws WriteException
	 */
	private function stamped(EntryChanges $changes): EntryChanges
	{
		return new EntryChanges(
			set: [...$changes->set, 'updated' => self::dateString($this->clock->now()->setTimezone($this->app->timezone()))],
			remove: array_values(array_diff($changes->remove, ['updated'])),
			body: $changes->body
		);
	}

	/**
	 * Returns a status kept for an Undo: `null` for none in the file.
	 */
	private static function statusFrom(mixed $value): ?EntryStatus
	{
		return is_string($value) ? EntryStatus::tryFrom($value) ?? EntryStatus::Draft : null;
	}

	/**
	 * Says that an entry is in the trash, so it can't be changed.
	 */
	public static function inTrash(Entry $entry): string
	{
		return sprintf('"%s" is in the trash; restore it to change it.', self::titleOf($entry));
	}

	/**
	 * Returns an entry's title, or "Untitled".
	 */
	private static function titleOf(Entry $entry): string
	{
		return trim($entry->title) === '' ? 'Untitled' : $entry->title;
	}

	/**
	 * Reads a date, in the site's timezone unless it says otherwise, or
	 * returns `null`.
	 */
	private static function date(?string $value, AppConfig $app): ?DateTimeImmutable
	{
		if ($value === null || trim($value) === '') {
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
	public static function dateString(DateTimeImmutable $date): string
	{
		return $date->format('Y-m-d H:i:s P');
	}
}
