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
use Blush\Activity\Activity;
use Blush\Activity\ActivityAction;
use Blush\Activity\ActivityException;
use Blush\Activity\ActivityLog;
use Blush\Activity\Actor;
use Blush\Auth\Account;
use Blush\Auth\AccountProfiles;
use Blush\Auth\Capability;
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
use Blush\Translation\Messages;
use Blush\Translation\Text;
use Blush\Workflow\Status as EntryStatus;
use Blush\Workflow\Statuses;
use Blush\Workflow\Transition;
use Blush\Workflow\Transitions;
use Blush\Workflow\Workflows;

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
 * result that's live needs to publish it; changing the authors so
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
 * **A change of status is a transition** in the type's workflow (D-851):
 * `Transitions` says whether the account may make it, and dispatches
 * `StatusChanging`, which a listener may refuse, and `StatusChanged`.
 * New, copied, and restored entries start at the workflow's `initial`.
 *
 * `status` is a shortcut: a status's name sets it (`published`, a file
 * without one, clears it), and a live one dates the entry now if it has
 * no `date` or a future one, or at the `date` given; `scheduled` moves
 * it into a live status and sets `date` (a future date, required).
 *
 * **A type's index page** (`IndexPage`, D-274) is edited without the
 * type's fields: only its title and status are. It's never scheduled or
 * dated by publishing, and never moved to the trash.
 *
 * Every write throws `EditRefused` when it's refused, saying why.
 */
final readonly class EntryEditor
{
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
		private LoggerInterface $logger,
		private Messages $messages,
		private Statuses $statuses,
		private Workflows $workflows,
		private Transitions $transitions
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

		return clone($this, ['permissions' => $permissions, 'html' => $this->html->withPermissions($permissions), 'transitions' => $this->transitions->withPermissions($permissions)]);
	}

	/**
	 * Creates an entry. New entries start at their workflow's `initial`
	 * unless asked otherwise (another status is a transition from it,
	 * D-851), and credit the account's author. A tree's page may name a `parent`
	 * (another of its pages, by key) to go under (D-408).
	 *
	 * @throws EditRefused
	 */
	public function create(Actor $actor, NewEntry $new): Entry
	{
		$account = $actor->account;
		$type    = $this->types->find($new->type);

		if (! $this->permissions->can($account, ContentAction::Create, $type?->name)) {
			throw EditRefused::forbidden($type === null
				? $this->messages->t('content.entry_editor.cannot_create_entries', 'You aren\'t allowed to create entries.')
				: $this->messages->t('content.entry_editor.cannot_create', 'You aren\'t allowed to create {items}.', items: $type->labels->items));
		}

		if ($type === null || trim($new->title) === '') {
			throw EditRefused::invalid('Send a known "type" and a "title".');
		}

		$slug    = $new->slug !== null && $new->slug !== '' ? $new->slug : Slug::from($new->title);
		$now     = $this->clock->now()->setTimezone($this->app->timezone());
		$initial = $this->workflows->for($type)->initial;

		try {
			$set = [
				'title' => $new->title,
				'date'  => self::dateString($now),
				...$this->authorDefault($account, $type->name),
				...$new->set
			];

			// The editor's body starts at its first line (D-334); the
			// file has a blank line before it.
			$changes = $this->withStatus(new EntryChanges(set: $set, body: DocumentEditor::gap($new->body) === '' ? "\n{$new->body}" : $new->body), $new->status ?? $initial, $new->date, null, $type, $initial);
		} catch (WriteException $e) {
			throw EditRefused::unprocessable($e->getMessage(), previous: $e);
		}

		// A new file without a status is published.
		$to = Transitions::stored($changes->set);

		if ($this->statuses->get($to)->live && ! $this->permissions->can($account, ContentAction::Publish, $type->name)) {
			throw EditRefused::forbidden($this->messages->t('content.entry_editor.new_must_be_draft', 'You aren\'t allowed to publish, so new entries must be drafts.'));
		}

		$this->checkMove($account, $type, $initial, $to);

		$html = $this->html->refusal($account, '', $changes->body ?? '');

		if ($html !== null) {
			throw EditRefused::forbidden($html, 'body');
		}

		$parent = null;

		if ($new->parent !== null && $new->parent !== '') {
			if (! $type instanceof Tree) {
				throw EditRefused::invalid($this->messages->t('content.entry_editor.not_a_tree', 'Only pages of a tree go under another; {items} don\'t.', items: $type->labels->items));
			}

			$parent = $this->content->named($type->name, $new->parent);

			if ($parent === null || $parent->landing) {
				throw EditRefused::unprocessable($this->messages->t('content.entry_editor.no_parent', 'There\'s no "{parent}" in {items} to put it under.', parent: $new->parent, items: $type->labels->items), 'parent');
			}
		}

		// Written in the site's language, then moved to another (D-728).
		$language = $new->language ?? $this->app->languages->default->code;
		$problem  = $this->app->languages->isDefault($language) ? null : $this->languageProblem($type, $parent === null ? $slug : "{$parent->key}/{$slug}", $language);

		if ($problem !== null) {
			throw EditRefused::unprocessable($problem, 'language');
		}

		$expiry = $this->expiryProblem($type, null, $changes->set);

		if ($expiry !== null) {
			throw EditRefused::unprocessable($expiry, 'expires');
		}

		$this->related($account, $type, null, [], $changes, $this->statuses->get($to)->live);

		try {
			$entry = $this->content->create($type, $slug, $changes, $parent, $now);
			$entry = $this->app->languages->isDefault($language) ? $entry : $this->content->changeLanguage($entry, $language);
		} catch (WriteException $e) {
			// A trashed entry keeps its address until it's deleted (D-484).
			$holder = $this->content->named($type->name, $parent === null ? $slug : "{$parent->key}/{$slug}");

			throw EditRefused::unprocessable($holder?->status->is(EntryStatus::TRASH) === true
				? $this->messages->t('content.entry_editor.address_in_trash', '“{title}” in the trash has this address; restore it, or delete it permanently, first.', title: $this->titleOf($holder))
				: $e->getMessage(), previous: $e);
		}

		$this->log($actor, ActivityAction::EntryCreate, $entry, $this->messages->t('content.entry_editor.summary.created', 'Created “{title}”', title: $this->titleOf($entry)));

		return $entry;
	}

	/**
	 * Changes an entry, at the revision that was read. A new `slug`
	 * renames it (D-277), first, so a slug that's refused changes nothing.
	 * With `redirect`, a published entry's old address redirects to it
	 * (D-680, D-686). A tree's page takes `parent` to move under it
	 * (D-410), with the pages under it, which get redirects too. A new
	 * date may rename a file its type's pattern names by date
	 * (D-519), the storage driver's to do.
	 *
	 * @throws EditRefused
	 */
	public function update(Actor $actor, Entry $entry, EntryEdit $edit, ?string $revision): Entry
	{
		$account = $actor->account;

		if (! $this->permissions->can($account, ContentAction::Edit, $entry)) {
			throw EditRefused::forbidden($this->messages->t('content.entry_editor.cannot_edit', 'You aren\'t allowed to edit that entry.'));
		}

		if ($entry->status->is(EntryStatus::TRASH)) {
			throw EditRefused::unprocessable($this->inTrash($entry));
		}

		if ($revision === null) {
			throw EditRefused::revisionRequired('Send the "revision" you loaded, so no one else\'s change is lost.');
		}

		$file = $this->editable($entry);
		$current = Transitions::stored($file->frontMatter);

		try {
			$changes = $this->stamped(new EntryChanges(set: $edit->set, remove: $edit->remove, body: $edit->body));
			$changes = $edit->status === null ? $changes : $this->withStatus($changes, $edit->status, $edit->date, $entry, $entry->type, $current);
		} catch (WriteException $e) {
			throw EditRefused::unprocessable($e->getMessage(), previous: $e);
		}

		// A change of status is a transition (D-851).
		$to   = self::storedAfter($changes, $current);
		$live = $this->statuses->get($to)->live;

		$this->checkMove($account, $entry, $current, $to);

		$refusal = $this->refusal($account, $entry, $changes, $live);

		if ($refusal !== null) {
			throw EditRefused::forbidden($refusal);
		}

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
				throw EditRefused::invalid($this->messages->t('content.entry_editor.cannot_move', 'Only a tree\'s pages move under another; "{title}" doesn\'t.', title: $entry->title));
			}

			$target = $edit->parent === '' ? null : $this->content->named($entry->type->name, $edit->parent);

			if ($edit->parent !== '' && ($target === null || $target->landing)) {
				throw EditRefused::unprocessable($this->messages->t('content.entry_editor.no_move_parent', 'There\'s no "{parent}" in {items} to move it under.', parent: $edit->parent, items: $entry->type->labels->items), 'parent');
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
			throw EditRefused::unprocessable($this->messages->t('content.entry_editor.page_taken', 'There\'s already a page at {page}.', page: ($folder === '.' ? '' : "{$folder}/") . $entry->slug), 'parent');
		}

		// Another language (D-728), checked before anything is written.
		$language = $edit->language !== null && $edit->language !== $entry->language ? $edit->language : null;

		if ($language !== null) {
			$problem = $entry->landing || $this->archivePages->isList($entry) || $this->archivePages->isTarget($entry) || ErrorPage::status($entry) !== null
				? $this->messages->t('content.entry_editor.keeps_language', '"{title}" keeps the language of the pages it goes with.', title: $entry->title)
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

		$expiry = $this->expiryProblem($entry->type, $entry, array_diff_key([...$file->frontMatter, ...$changes->set], array_flip($changes->remove)));

		if ($expiry !== null) {
			throw EditRefused::unprocessable($expiry, 'expires');
		}

		try {
			$this->related($account, $entry->type, $entry, $file->frontMatter, $changes, $live);
		} catch (WriteException $e) {
			throw EditRefused::unprocessable($e->getMessage(), previous: $e);
		}

		$transition = $this->transitions->between($entry, $current, $to);

		$this->announce($actor, $entry, $current, $to, $transition);

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
		$this->transitions->changed($actor, $updated, $current, $to, $transition);

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
				? $this->messages->t('content.entry_editor.passage_missing', 'The body of “{title}” doesn\'t have that passage; read it again and copy the passage exactly.', title: $this->titleOf($entry))
				: $this->messages->t('content.entry_editor.passage_repeated', 'The body of “{title}” has that passage {count, plural, one {# time} other {# times}}; send more of the text around it so it\'s found once.', title: $this->titleOf($entry), count: $count), 'find');
		}

		return $this->update($actor, $entry, new EntryEdit(body: str_replace($find, $replace, $body)), $revision);
	}

	/**
	 * Moves an entry to a status (or `scheduled`), by the transition to
	 * it in its workflow (D-851), at its current revision unless one is
	 * given (a bulk change's, D-301, loses no one's writing). Going live
	 * needs its required fields filled and its relations within their
	 * limits, and dates an undated entry.
	 *
	 * @throws EditRefused
	 */
	public function setStatus(Actor $actor, Entry $entry, string $status, ?string $revision = null, ?string $date = null): Entry
	{
		if ($entry->status->is(EntryStatus::TRASH)) {
			throw EditRefused::unprocessable($this->inTrash($entry));
		}

		if (! $this->permissions->can($actor->account, ContentAction::Edit, $entry)) {
			throw EditRefused::forbidden($this->messages->t('content.entry_editor.cannot_edit', 'You aren\'t allowed to edit that entry.'));
		}

		$file = $this->editable($entry);
		$from = Transitions::stored($file->frontMatter);
		$to   = $status === EntryStatus::SCHEDULED ? $this->liveTarget($entry->type, $from) : $status;

		$this->checkMove($actor->account, $entry, $from, $to);

		if ($this->statuses->get($to)->live) {
			$missing = $this->missing($entry) ?? $this->outOfLimits($entry);

			if ($missing !== null) {
				throw EditRefused::unprocessable($missing);
			}
		}

		return $this->update($actor, $entry, new EntryEdit(status: $status, date: $date), $revision ?? $file->version);
	}

	/**
	 * Makes a transition by name (D-851): moves an entry to its status,
	 * as `setStatus()` does, when the account may make it from the
	 * entry's status. A `date` still to come schedules it.
	 *
	 * @throws EditRefused
	 */
	public function transition(Actor $actor, Entry $entry, string $name, ?string $revision = null, ?string $date = null): Entry
	{
		$from       = Transitions::stored($this->editable($entry)->frontMatter);
		$transition = $this->transitions->named($actor->account, $entry, $from, $name);

		if ($transition === null) {
			$known = $this->workflows->for($entry->type)->transition($name);

			throw $known === null
				? EditRefused::invalid($this->messages->t('content.entry_editor.no_transition', 'There\'s no "{transition}" transition for {items}.', items: $entry->type->labels->items, transition: $name), 'transition')
				: EditRefused::forbidden($this->transitions->refusal($actor->account, $entry, $from, $known->to) ?? $this->messages->t('content.entry_editor.transition_not_here', '“{title}” can\'t {transition} from {status}.', title: $this->titleOf($entry), transition: (string) $known->label, status: (string) $this->statuses->get($from)->label), 'transition');
		}

		return $this->setStatus($actor, $entry, $transition->to, $revision, $date);
	}

	/**
	 * Moves an entry whose status isn't in its type's workflow (or that
	 * nothing registers) to the workflow's `initial` (D-853): Site Health's
	 * fix, which needs `site.health`. It isn't a transition, as Undo isn't:
	 * the status is set directly, and the events say so (no transition).
	 *
	 * @throws EditRefused
	 */
	public function resetStatus(Actor $actor, Entry $entry): Entry
	{
		if (! $this->permissions->can($actor->account, Capability::SiteHealth)) {
			throw EditRefused::forbidden($this->messages->t('content.entry_editor.cannot_reset', 'You aren\'t allowed to fix what Site Health finds.'));
		}

		if ($entry->status->is(EntryStatus::TRASH)) {
			throw EditRefused::unprocessable($this->inTrash($entry));
		}

		$file     = $this->editable($entry);
		$from     = Transitions::stored($file->frontMatter);
		$workflow = $this->workflows->for($entry->type);
		$to       = $workflow->initial;

		if ($workflow->has($from)) {
			throw EditRefused::unprocessable($this->messages->t('content.entry_editor.in_workflow', '“{title}” already has a status of its workflow.', title: $this->titleOf($entry)), 'status');
		}

		try {
			$changes = $this->stamped($to === EntryStatus::PUBLISHED ? new EntryChanges(remove: ['status']) : new EntryChanges(set: ['status' => $to]));
		} catch (WriteException $e) {
			throw EditRefused::unprocessable($e->getMessage(), previous: $e);
		}

		$this->announce($actor, $entry, $from, $to, null);

		try {
			$updated = $this->content->change($entry, $changes, $file->version);
		} catch (WriteConflict $e) {
			throw EditRefused::conflict($e->getMessage(), $e);
		} catch (WriteException $e) {
			throw EditRefused::unprocessable($e->getMessage(), previous: $e);
		}

		$this->log($actor, ActivityAction::EntryStatus, $updated, $this->messages->t('content.entry_editor.summary.moved_to', 'Moved “{title}” to {status}', title: $this->titleOf($updated), status: (string) $updated->status->label), self::before($entry, $file));
		$this->transitions->changed($actor, $updated, $from, $to, null);

		return $updated;
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
			throw EditRefused::forbidden($this->messages->t('content.entry_editor.cannot_delete', 'You aren\'t allowed to delete that entry.'));
		}

		if ($entry->status->is(EntryStatus::TRASH)) {
			throw EditRefused::unprocessable($this->messages->t('content.entry_editor.already_trashed', '"{title}" is already in the trash; delete it permanently, or restore it.', title: $entry->title));
		}

		if (IndexPage::is($entry)) {
			throw EditRefused::unprocessable($this->messages->t('content.entry_editor.index_not_trashed', '"{title}" is the index page for {items}, so it can\'t be moved to the trash.', title: $entry->title, items: $entry->type->labels->items));
		}

		if ($revision === null) {
			throw EditRefused::revisionRequired('Send the "revision" you loaded, so no one else\'s change is lost.');
		}

		try {
			$file     = $this->content->editable($entry);
			$declared = $file->frontMatter['status'] ?? null;
			$from     = Transitions::stored($file->frontMatter);

			$this->announce($actor, $entry, $from, EntryStatus::TRASH, null);

			$trashed = $this->content->trash($entry, $revision);
		} catch (WriteConflict $e) {
			throw EditRefused::conflict($e->getMessage(), $e);
		} catch (WriteException $e) {
			throw EditRefused::unprocessable($e->getMessage(), previous: $e);
		}

		$declared = is_string($declared) ? $declared : null;

		$this->log($actor, ActivityAction::EntryTrash, $trashed, $this->messages->t('content.entry_editor.summary.trashed', 'Moved “{title}” to the trash', title: $this->titleOf($entry)), ['status' => $declared]);
		$this->transitions->changed($actor, $trashed, $from, EntryStatus::TRASH, null);

		return [$trashed, $declared];
	}

	/**
	 * Brings an entry back from the trash at its workflow's `initial`
	 * (`''`, D-237, D-851), or, for an Undo, with the status it had
	 * (`null` for none, so published), only at the revision moving it
	 * there wrote (D-525).
	 *
	 * @throws EditRefused
	 */
	public function restore(Actor $actor, Entry $entry, ?string $status = '', ?string $revision = null): Entry
	{
		$initial = $this->workflows->for($entry->type)->initial;
		$status  = $status === '' ? $initial : $status;

		if (! $entry->status->is(EntryStatus::TRASH)) {
			throw EditRefused::notFound($this->messages->t('content.entry_editor.not_trashed', 'That isn\'t in the trash.'));
		}

		if (! $this->permissions->can($actor->account, ContentAction::Delete, $entry)) {
			throw EditRefused::forbidden($this->messages->t('content.entry_editor.cannot_restore', 'You aren\'t allowed to restore that entry.'));
		}

		if ($status !== null && ! array_key_exists($status, $this->statuses->registered())) {
			throw EditRefused::unprocessable('An entry is restored with a registered status\'s name, or `null` (no status, so published).');
		}

		if ($status !== $initial && $revision === null) {
			throw EditRefused::revisionRequired('Send the "revision" moving it to the trash answered, so it\'s put back only as it was.');
		}

		if (($status === null || $this->statuses->get($status)->live) && ! $this->permissions->can($actor->account, ContentAction::Publish, $entry)) {
			throw EditRefused::forbidden($this->messages->t('content.entry_editor.restore_as_draft', 'You aren\'t allowed to publish that entry, so it can\'t be put back as it was; restore it as a draft.'));
		}

		$to = $status ?? EntryStatus::PUBLISHED;

		$this->announce($actor, $entry, EntryStatus::TRASH, $to, null);

		try {
			$restored = $this->content->restore($entry, $revision, $status);
		} catch (WriteConflict $e) {
			throw EditRefused::conflict($this->messages->t('content.entry_editor.changed_in_trash', '"{title}" changed in the trash since it was moved there, so it can\'t be put back as it was; restore it from the Trash.', title: $entry->title), $e);
		} catch (WriteException $e) {
			throw EditRefused::unprocessable($e->getMessage(), previous: $e);
		}

		$this->log($actor, ActivityAction::EntryRestore, $restored, $this->messages->t('content.entry_editor.summary.restored', 'Restored “{title}” from the trash', title: $this->titleOf($entry)), ['trash' => true]);
		$this->transitions->changed($actor, $restored, EntryStatus::TRASH, $to, null);

		return $restored;
	}

	/**
	 * Copies an entry at its workflow's `initial` (D-275, D-851), titled
	 * "… (Copy)", slugged
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
			throw EditRefused::forbidden($this->messages->t('content.entry_editor.cannot_duplicate', 'You aren\'t allowed to duplicate that entry.'));
		}

		if ($entry->status->is(EntryStatus::TRASH)) {
			throw EditRefused::unprocessable($this->inTrash($entry));
		}

		if (IndexPage::is($entry)) {
			throw EditRefused::unprocessable($this->messages->t('content.entry_editor.only_one_index', '"{title}" is the index page for {items}, and there\'s only one.', title: $entry->title, items: $entry->type->labels->items));
		}

		if ($entry->landing) {
			throw EditRefused::unprocessable($this->messages->t('content.entry_editor.only_one_root', '"{title}" is the site\'s root page, and there\'s only one.', title: $entry->title));
		}

		$now   = $this->clock->now()->setTimezone($this->app->timezone());
		$title = (string) $this->messages->t('content.entry_editor.copy_title', '{title} (Copy)', title: $this->titleOf($entry));
		$initial = $this->workflows->for($entry->type)->initial;
		$set     = [
			'title'     => $title,
			'date'      => self::dateString($now),
			...($initial === EntryStatus::PUBLISHED ? [] : ['status' => $initial])
		];

		try {
			// The copy's name is its file's, and the original's old
			// addresses stay the original's.
			$copy = $this->content->duplicate($entry, Slug::from(($entry->slug === '' ? $title : $entry->slug) . '-copy'), new EntryChanges(set: $set, remove: $initial === EntryStatus::PUBLISHED ? ['slug', 'status'] : ['slug']), $now);
		} catch (WriteException $e) {
			throw EditRefused::unprocessable($e->getMessage(), previous: $e);
		}

		$this->log($actor, ActivityAction::EntryCreate, $copy, $this->messages->t('content.entry_editor.summary.copied', 'Copied “{title}” as “{copy}”', title: $this->titleOf($entry), copy: $title));

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
			throw EditRefused::notFound($this->messages->t('content.entry_editor.gone', '“{title}” is no longer there.', title: $activity->title));
		}

		if (! $activity->canUndo()) {
			throw EditRefused::unprocessable($activity->undone !== null
				? $this->messages->t('content.entry_editor.already_undone', 'That change was already undone.')
				: $this->messages->t('content.entry_editor.cannot_undo', 'That change can\'t be undone.'));
		}

		$before = $activity->before ?? [];
		$file   = $this->editable($entry);

		$moved = is_string($before['key'] ?? null) && $before['key'] !== $entry->key;

		if ($moved || ($activity->revision !== null && $file->version !== $activity->revision)) {
			throw EditRefused::conflict($this->messages->t('content.entry_editor.changed_since', '“{title}” changed since; open it to edit.', title: $this->titleOf($entry)));
		}

		$actor = $actor->undoing($activity);

		$undone = match ($activity->action) {
			ActivityAction::EntryTrash   => $this->restore($actor, $entry, $this->statusFrom($before['status'] ?? null), $file->version),
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
			throw EditRefused::unprocessable($this->messages->t('content.entry_editor.nothing_kept', 'That change didn\'t keep what it replaced, so it can\'t be undone.'));
		}

		$set = [];

		foreach ($frontMatter as $key => $value) {
			$set[(string) $key] = $value;
		}

		$remove = array_values(array_diff(array_map(strval(...), array_keys($file->frontMatter)), array_keys($set)));
		$from   = Transitions::stored($file->frontMatter);
		$to     = Transitions::stored($set);

		if ($this->statuses->get($to)->live && ! $this->permissions->can($actor->account, ContentAction::Publish, $entry)) {
			throw EditRefused::forbidden($this->messages->t('content.entry_editor.undo_needs_publish', 'You aren\'t allowed to publish that entry, so this change can\'t be undone.'));
		}

		if (! $this->permissions->can($actor->account, ContentAction::Edit, $entry)) {
			throw EditRefused::forbidden($this->messages->t('content.entry_editor.cannot_edit', 'You aren\'t allowed to edit that entry.'));
		}

		$this->announce($actor, $entry, $from, $to, null);

		try {
			$restored = $this->content->change($entry, new EntryChanges(set: $set, remove: $remove, body: $body), $file->version);
		} catch (WriteConflict $e) {
			throw EditRefused::conflict($e->getMessage(), $e);
		} catch (WriteException $e) {
			throw EditRefused::unprocessable($e->getMessage(), previous: $e);
		}

		$this->log($actor, ActivityAction::EntryUpdate, $restored, $this->messages->t('content.entry_editor.summary.undid', 'Undid a change to “{title}”', title: $this->titleOf($restored)), self::before($entry, $file));
		$this->transitions->changed($actor, $restored, $from, $to, null);

		return $restored;
	}

	/**
	 * Records a change in the activity log, with the version it wrote. A
	 * log that can't be written is logged; the change stands.
	 *
	 * @param ?array<string, mixed> $before
	 */
	private function log(Actor $actor, ActivityAction $action, Entry $entry, Text $summary, ?array $before = null): void
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
			$this->activity->record($actor, $action, $entry->id, $this->titleOf($entry), $summary, $before, $revision);
		} catch (ActivityException $e) {
			$this->logger->warning('A change to "{title}" couldn\'t be added to the activity log: {problem}', ['title' => $entry->title, 'problem' => $e->getMessage()]);
		}
	}

	/**
	 * Says what an update changed, for the activity log: a message, kept
	 * as one and shown in the reader's language (D-836), with what changed
	 * as messages of its own, joined by `summary.list`.
	 */
	private function updateSummary(Entry $was, Entry $now, EntryEdit $edit, EditableEntry $file): Text
	{
		$title = $this->titleOf($now);

		if ($edit->isStatusOnly()) {
			$when = $now->date?->format('Y-m-d H:i');

			return match ($now->status->name) {
				EntryStatus::PUBLISHED => $this->messages->t('content.entry_editor.summary.published', 'Published “{title}”', title: $title),
				EntryStatus::SCHEDULED => $when === null
					? $this->messages->t('content.entry_editor.summary.scheduled', 'Scheduled “{title}” for later', title: $title)
					: $this->messages->t('content.entry_editor.summary.scheduled_for', 'Scheduled “{title}” for {when}', title: $title, when: $when),
				EntryStatus::DRAFT     => $this->messages->t('content.entry_editor.summary.drafted', 'Moved “{title}” to draft', title: $title),
				default                => $this->messages->t('content.entry_editor.summary.moved_to', 'Moved “{title}” to {status}', title: $title, status: (string) $now->status->label)
			};
		}

		$parts = [];

		if ($edit->body !== null && $edit->body !== $file->body) {
			$parts[] = $this->messages->t('content.entry_editor.summary.body', 'the body');
		}

		foreach (array_values(array_diff([...array_keys($edit->set), ...$edit->remove], ['updated', 'status', 'date'])) as $field) {
			$parts[] = $field;
		}

		if ($was->slug !== $now->slug || $was->key !== $now->key) {
			$parts[] = $this->messages->t('content.entry_editor.summary.address', 'its address');
		}

		if ($parts === []) {
			return $this->messages->t('content.entry_editor.summary.edited', 'Edited “{title}”', title: $title);
		}

		$changed = array_shift($parts);

		foreach ($parts as $part) {
			$changed = $this->messages->t('content.entry_editor.summary.list', '{list}, {item}', list: $changed, item: $part);
		}

		return $this->messages->t('content.entry_editor.summary.edited_parts', 'Edited {changed} of “{title}”', changed: $changed, title: $title);
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
	 * fields, so it has none; the `date` isn't checked, since
	 * publishing sets it.
	 */
	private function missing(Entry $entry): ?Text
	{
		$empty = IndexPage::is($entry) ? [] : array_filter($entry->type->schema->fields, static function (Field $field) use ($entry): bool {
			$value = $field->name === 'title' ? $entry->title : $entry->field($field->name);

			return $field->required
				&& $field->name !== 'date'
				&& ($value === null || $value === [] || (is_string($value) && trim($value) === ''));
		});

		$names = array_values(array_map(static fn (Field $field): string => $field->label === '' ? ucfirst($field->name) : $field->label, $empty));

		return $names === [] ? null : $this->messages->t(
			'content.entry_editor.required',
			'{count, plural, one {{names} is required to publish.} other {{names} are required to publish.}}',
			count: count($names),
			names: implode(', ', $names)
		);
	}

	/**
	 * Checks a save's relations (D-596), and writes the targets it creates
	 * as they're typed, before the entry is written.
	 *
	 * @param  array<array-key, mixed> $before The entry's front matter, none for a new one.
	 * @param  bool                    $live   Whether the save leaves the entry live.
	 * @throws EditRefused When the account may not create and publish entries of a target's type, or a save that leaves the entry live leaves a relation out of its limits.
	 * @throws WriteException
	 */
	private function related(Account $account, ContentType $type, ?Entry $entry, array $before, EntryChanges $changes, bool $live): void
	{
		$after    = array_diff_key([...$before, ...$changes->set], array_flip($changes->remove));
		$language = $entry->language ?? $this->app->languages->default->code;
		$found    = $this->typed->find($type->name, $language, $before, $after);

		foreach ($found as $name => $byType) {
			foreach ($byType as $target => $titles) {
				if (! $this->permissions->can($account, ContentAction::Create, $target) || ! $this->permissions->can($account, ContentAction::Publish, $target)) {
					throw EditRefused::forbidden(
						$this->messages->t('content.entry_editor.cannot_add_targets', 'You aren\'t allowed to add new {items}: {titles}.', items: $this->types->find($target)->labels->items ?? $target, titles: implode(', ', $titles)),
						$this->relations->find($type->name, $name)->field ?? $name
					);
				}
			}
		}

		if ($live) {
			$problems = $this->limits->check($type->name, $entry?->id, $language, $after);

			if ($problems !== []) {
				$relation = $this->relations->byKey($problems[0]->key);
				$full     = $relation !== null && $problems[0]->kind === ProblemKind::InverseLimit ? $this->full($relation, $problems[0]->value, $entry->id ?? '') : null;

				throw EditRefused::unprocessable(
					implode(' ', array_map(static fn (RelationProblem $problem): string => (string) $problem->message, $problems)),
					$relation?->field,
					$full === null ? [] : ['full' => $full]
				);
			}
		}

		$created = $found === [] ? null : $this->typed->create($found);

		if ($created !== null && $created->failed !== []) {
			throw EditRefused::unprocessable($this->messages->t('content.entry_editor.targets_failed', '{keys} couldn\'t be written: {problems}', keys: implode(', ', array_keys($created->failed)), problems: implode(' ', $created->failed)));
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

		return $problems === [] ? null : implode(' ', array_map(static fn (RelationProblem $problem): string => (string) $problem->message, $problems));
	}

	/**
	 * Says what's wrong with an entry's (or a type's new one's) `expires`
	 * date in its front matter after a change, or `null` (D-852): a type
	 * without expiry takes none, nor do index, archive, error, and
	 * landing pages, and it comes after the entry's `date`.
	 *
	 * @param array<array-key, mixed> $after
	 */
	private function expiryProblem(ContentType $type, ?Entry $entry, array $after): ?Text
	{
		$expires = $this->timeOf($after['expires'] ?? null);

		if ($expires === null) {
			return null;
		}

		if (! $type->expires) {
			return $this->messages->t('content.entry_editor.no_expiry', 'Expiry isn\'t on for {items}, so they can\'t have an "expires" date.', items: $type->labels->items);
		}

		if ($entry !== null && ($entry->landing || IndexPage::is($entry) || $this->archivePages->isList($entry) || $this->archivePages->isTarget($entry) || ErrorPage::status($entry) !== null)) {
			return $this->messages->t('content.entry_editor.never_expires', '“{title}” can\'t expire: index, archive, error, and landing pages stay as long as their site does.', title: $this->titleOf($entry));
		}

		$date = $this->timeOf($after['date'] ?? null);

		return $date !== null && $expires <= $date
			? $this->messages->t('content.entry_editor.expires_before_date', 'The expiry date has to come after the entry\'s date.')
			: null;
	}

	/**
	 * Reads a front matter date as a Unix time, or returns `null`.
	 */
	private function timeOf(mixed $value): ?int
	{
		return match (true) {
			is_int($value)                         => $value,
			$value instanceof DateTimeImmutable    => $value->getTimestamp(),
			is_string($value)                      => self::date($value, $this->app)?->getTimestamp(),
			default                                => null
		};
	}

	/**
	 * Returns what's wrong with renaming an entry to a slug, or `null`,
	 * in the folder of keys it's in, or moving to (`.` for the top).
	 */
	private function slugProblem(Entry $entry, string $slug, ?string $folder = null): ?Text
	{
		if ($entry->landing) {
			return $this->messages->t('content.entry_editor.landing_slug', '"{title}" is its folder\'s landing page, so its slug is the folder\'s.', title: $entry->title);
		}

		if (ErrorPage::status($entry) !== null && $slug !== $entry->slug) {
			return $this->messages->t('content.entry_editor.error_slug', '"{title}" is the page for error {status}, so its slug is the status.', title: $entry->title, status: $entry->slug);
		}

		if (! Slug::isSlug($slug)) {
			return $this->messages->t('content.entry_editor.bad_slug', 'Slugs are lowercase letters, numbers, and hyphens; try "{slug}".', slug: Slug::from($slug) ?: 'untitled');
		}

		$folder ??= dirname($entry->key);
		$key      = ($folder === '.' ? '' : "{$folder}/") . $slug;

		return match ($this->content->named($entry->type->name, $key)?->status->name) {
			null              => null,
			EntryStatus::TRASH => $this->messages->t('content.entry_editor.slug_in_trash', 'A {item} in the trash has the slug "{slug}"; restore it, or delete it permanently, to use it.', item: $entry->type->labels->item, slug: $slug),
			default           => $this->messages->t('content.entry_editor.slug_taken', 'Another {item} already has the slug "{slug}".', item: $entry->type->labels->item, slug: $slug)
		};
	}

	/**
	 * Returns what's wrong with an entry of a type at a key being in a
	 * language (D-728), or `null`: a language the site doesn't have, one
	 * its translations already have, or a key that's taken there.
	 */
	private function languageProblem(ContentType $type, string $key, string $code, ?Entry $entry = null): ?Text
	{
		$language = $this->app->languages->find($code);

		if ($language === null) {
			return $this->messages->t('content.entry_editor.no_language', 'The site has no language "{code}".', code: $code);
		}

		$translation = $entry === null ? null : $this->content->translations($entry)[$code] ?? null;

		if ($translation !== null && $translation->id !== $entry->id) {
			return $this->messages->t('content.entry_editor.translated', 'Its {language} translation is “{title}”.', language: $language->name(), title: $this->titleOf($translation));
		}

		$found = $this->content->named($type->name, $key, $code);

		return $found === null || $found->id === $entry?->id ? null : $this->messages->t('content.entry_editor.taken_in_language', 'There\'s already a {item} at {page} in {language}.', item: $type->labels->item, page: $key, language: $language->name());
	}

	/**
	 * Returns why the account may not make a change that leaves the entry
	 * live, or not, or `null`.
	 */
	private function refusal(Account $account, Entry $entry, EntryChanges $changes, bool $live): ?Text
	{
		if ($live && ! $this->permissions->can($account, ContentAction::Publish, $entry)) {
			return $this->messages->t('content.entry_editor.cannot_publish', 'You aren\'t allowed to publish that entry; keep it a draft.');
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
			: $this->messages->t('content.entry_editor.keep_yourself', 'You can add authors, but not take yourself off an entry.');
	}

	/**
	 * Applies the status shortcut: a status's name, or `scheduled`, for an
	 * entry (`null` for a new one) of a type, stored with a status now.
	 *
	 * @throws EditRefused
	 * @throws WriteException
	 */
	private function withStatus(EntryChanges $changes, string $status, ?string $date, ?Entry $entry, ContentType $type, string $from): EntryChanges
	{
		$workflow  = $this->workflows->for($type);
		$scheduled = $status === EntryStatus::SCHEDULED;

		if (! $scheduled && ! $workflow->has($status) && $status !== $from) {
			throw EditRefused::invalid(sprintf('"status" must be one of %s.', implode(', ', [...$workflow->statuses, EntryStatus::SCHEDULED])), 'status');
		}

		$set    = $changes->set;
		$remove = array_values(array_diff($changes->remove, ['status']));
		$now    = $this->clock->now();
		$index  = $entry !== null && IndexPage::is($entry);
		$target = $scheduled ? $this->liveTarget($type, $from) : $status;
		$live   = $this->statuses->get($target)->live;

		if ($scheduled && $index) {
			throw EditRefused::invalid($this->messages->t('content.entry_editor.index_not_scheduled', 'An index page can\'t be scheduled; publish it or keep it a draft.'), 'status');
		}

		unset($set['status']);

		// A file without a status is published.
		if ($target === EntryStatus::PUBLISHED) {
			$remove[] = 'status';
		} else {
			$set['status'] = $target;
		}

		$when = self::date($date, $this->app);

		if ($scheduled) {
			if ($when === null || $when <= $now) {
				throw EditRefused::invalid($this->messages->t('content.entry_editor.future_date', 'Scheduling needs a future "date".'), 'date');
			}

			$set['date'] = self::dateString($when);
		} elseif ($live && ! $index && ! isset($set['date'])) {
			if ($when !== null) {
				$set['date'] = self::dateString($when);
			} elseif ($entry?->date === null || $entry->date > $now) {
				$set['date'] = self::dateString($now->setTimezone($this->app->timezone()));
			}
		}

		// A new entry has no file yet, so there's no status key to clear.
		if ($entry === null) {
			$remove = [];
		}

		return new EntryChanges($set, array_values(array_unique($remove)), $changes->body);
	}

	/**
	 * Returns the live status scheduling moves an entry of a type into
	 * from a stored status: that status, when it's live; else the first
	 * live one a transition from it goes to; else the first live one in
	 * its workflow; else `published`.
	 */
	private function liveTarget(ContentType $type, string $from): string
	{
		if ($this->statuses->get($from)->live) {
			return $from;
		}

		$workflow = $this->workflows->for($type);
		$live     = fn (string $status): bool => $this->statuses->get($status)->live;

		$transition = array_find($workflow->from($from), static fn (Transition $transition): bool => $live($transition->to));

		return $transition->to ?? array_find($workflow->statuses, $live) ?? EntryStatus::PUBLISHED;
	}

	/**
	 * Returns the status an entry is stored with after some changes, from
	 * the one it's stored with now.
	 */
	private static function storedAfter(EntryChanges $changes, string $from): string
	{
		if (array_key_exists('status', $changes->set)) {
			return Transitions::stored(['status' => $changes->set['status']]);
		}

		return in_array('status', $changes->remove, true) ? EntryStatus::PUBLISHED : $from;
	}

	/**
	 * Refuses moving an entry (or a type's new one) from a stored status
	 * to another that has no transition in its workflow, or that the
	 * account may not make (D-851).
	 *
	 * @throws EditRefused
	 */
	private function checkMove(Account $account, Entry|ContentType $on, string $from, string $to): void
	{
		$refusal = $this->transitions->refusal($account, $on, $from, $to);

		if ($refusal !== null) {
			throw $this->transitions->isMissing($on, $from, $to) ? EditRefused::unprocessable($refusal, 'status') : EditRefused::forbidden($refusal, 'status');
		}
	}

	/**
	 * Dispatches `StatusChanging` for a change about to be written, and
	 * refuses it with the reason a listener gave.
	 *
	 * @throws EditRefused
	 */
	private function announce(Actor $actor, Entry $entry, string $from, string $to, ?Transition $transition): void
	{
		$reason = $this->transitions->changing($actor, $entry, $from, $to, $transition);

		if ($reason !== null) {
			throw EditRefused::unprocessable($reason instanceof Text ? $reason : (string) $reason, 'status');
		}
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
	 * Returns a status kept for an Undo: `null` for none in the file,
	 * and a draft for one nothing registers now.
	 */
	private function statusFrom(mixed $value): ?string
	{
		return is_string($value) ? (array_key_exists($value, $this->statuses->registered()) ? $value : EntryStatus::DRAFT) : null;
	}

	/**
	 * Says that an entry is in the trash, so it can't be changed.
	 */
	public function inTrash(Entry $entry): Text
	{
		return $this->messages->t('content.entry_editor.in_trash', '"{title}" is in the trash; restore it to change it.', title: $this->titleOf($entry));
	}

	/**
	 * Returns an entry's title, or "Untitled".
	 */
	private function titleOf(Entry $entry): string
	{
		return trim($entry->title) === '' ? (string) $this->messages->t('content.entry_editor.untitled', 'Untitled') : $entry->title;
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
