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

namespace Blush\Admin\Http;

use DateTimeImmutable;
use DateTimeInterface;
use JsonException;
use LogicException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Admin\EntryHandles;
use Blush\Admin\EntryTemplates;
use Blush\Admin\Homepage;
use Blush\Admin\InvalidEdit;
use Blush\Auth\Account;
use Blush\Auth\Accounts;
use Blush\Auth\Capability;
use Blush\Auth\ContentAction;
use Blush\Auth\Permissions;
use Blush\Content\Entries;
use Blush\Content\Entry\Entry;
use Blush\Content\Entry\Position;
use Blush\Content\EntryFields;
use Blush\Content\Lint\Linter;
use Blush\Content\Relation\LinkResolver;
use Blush\Content\Relation\Referrers;
use Blush\Content\Relation\Relation;
use Blush\Content\Relation\Relations;
use Blush\Content\Relation\TranslationRule;
use Blush\Content\RelationArchives;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Routing\RedirectWriter;
use Blush\Content\Status as EntryStatus;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\DateArchives;
use Blush\Content\Type\Tree;
use Blush\Content\Writer\DocumentEditor;
use Blush\Content\Writer\EditableEntry;
use Blush\Content\Writer\WriteConflict;
use Blush\Content\Writer\WriteException;
use Blush\Content\Editing\IndexPage;
use Blush\Content\Editing\ErrorPage;
use Blush\Activity\ActivityException;
use Blush\Activity\ActivityLog;
use Blush\Activity\Actor;
use Blush\Activity\Via;
use Blush\Content\Editing\ArchivePages;
use Blush\Content\Editing\EditRefused;
use Blush\Content\Editing\EntryEdit;
use Blush\Content\Editing\EntryEditor;
use Blush\Content\Editing\NewEntry;
use Blush\Core\AppConfig;
use Blush\Field\ControlDefaults;
use Blush\Field\FormField;
use Blush\Field\Field;
use Blush\Field\FieldSet;
use Blush\Field\FieldTargets;
use Blush\Field\Violation;
use Blush\Http\Response;
use Blush\Media\MediaValues;
use Blush\Http\Status;

/**
 * The admin's editing API (D-229), over `Entries`' writes by id (D-654):
 *
 * - `GET    entries/{id}`: an entry for editing, by its id (D-481; a
 *   file without one can't be edited until it has one): its source
 *   `path`, its field values by name
 *   (from whichever key or alias the file uses), other front matter,
 *   body, revision, when the file was last written (`modified`), type
 *   and field descriptions, what the account may do, and the file's
 *   problems, and its handle (`EntryHandles`, D-253), or `null`; and
 *   the `templates` its Template row offers (D-783: `name`, `label`,
 *   `description`; none on a landing, archive, or error page). A `set`
 *   of `template` on `POST` or `PATCH` must name one of them, or it's a
 *   422 with `field: "template"`.
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
 *   address redirects to it, a row in the `redirects` table naming the
 *   entry by id (`RedirectWriter`, D-680, D-686). A tree's page takes `parent`
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
	 * What a bulk change can do, and the most entries it takes.
	 */
	private const array BULK_ACTIONS = ['publish', 'draft', 'trash'];

	private const int BULK_LIMIT = 100;

	/**
	 * Declared fields the editor never offers: `trashed` is the trash's
	 * (D-484), `translation_of` links a translation (D-511), and a
	 * profile's `linkable` is locked on its own screen (D-605).
	 */
	private const array NOT_EDITED = [EntryFields::TRASHED, EntryFields::TRANSLATION_OF, 'linkable'];

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
		private FieldTargets $targets,
		private Accounts $accounts,
		private Homepage $homepage,
		private Relations $relations,
		private RelationArchives $relationArchives,
		private Referrers $referrers,
		private ArchivePages $archivePages,
		private EntryEditor $editor,
		private ActivityLog $activity,
		private MediaValues $media,
		private EntryTemplates $templates,
		private ControlDefaults $defaults
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

		$fields = array_filter($this->types->formSchema($type->name)->fields, fn (Field $field): bool => $this->groups($field, $type, []) && ! in_array($field->name, self::NOT_EDITED, true));

		return self::json([
			'path'        => null,
			'id'          => null,
			'handle'      => null,
			'slug'        => '',
			'key'         => '',
			'language'    => $this->app->languages->default->code,
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
			'templates'   => $this->templatesFor($type->name),
			'values'      => $this->editor->authorDefault($account, $type->name),
			'extra'       => [],
			'body'        => '',
			'can'         => [
				'edit'         => true,
				'publish'      => $this->permissions->can($account, ContentAction::Publish, $type->name),
				'rename'       => true,
				'language'     => $this->app->languages->isMultilingual(),
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
		$refused = $this->templateRefused($input, is_string($input['type'] ?? null) ? $input['type'] : '');

		if ($refused !== null) {
			return $refused;
		}

		try {
			$entry = $this->editor->create(Actor::admin($account), new NewEntry(
				type: is_string($input['type'] ?? null) ? $input['type'] : '',
				title: is_string($input['title'] ?? null) ? $input['title'] : '',
				slug: is_string($input['slug'] ?? null) ? $input['slug'] : null,
				set: self::map($input, 'set'),
				body: self::stringOr($input, 'body', ''),
				status: self::stringOr($input, 'status', 'draft'),
				published: is_string($input['published'] ?? null) ? $input['published'] : null,
				parent: self::parentOf($input),
				language: is_string($input['language'] ?? null) ? $input['language'] : null
			));

			$file = $this->content->editable($entry);
		} catch (InvalidEdit $e) {
			return self::error($e->getMessage(), Status::BadRequest);
		} catch (EditRefused $e) {
			return self::refused($e);
		} catch (WriteException $e) {
			return self::error($e->getMessage(), Status::UnprocessableContent);
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

		$refused = $this->templateRefused($input, $entry->type->name);

		if ($refused !== null) {
			return $refused;
		}

		try {
			$body   = $input['body'] ?? null;
			$status = $input['status'] ?? null;
			$parent = array_key_exists('parent', $input) ? $input['parent'] : null;

			if ($parent !== null && ! is_string($parent)) {
				return self::error(sprintf('Only a tree\'s pages move under another; "%s" doesn\'t.', $entry->title), Status::BadRequest);
			}

			$edit = new EntryEdit(
				set: self::map($input, 'set'),
				remove: self::list($input, 'remove'),
				body: is_string($body) ? $body : null,
				status: $status === null ? null : (is_string($status) ? $status : ''),
				published: is_string($input['published'] ?? null) ? $input['published'] : null,
				slug: is_string($input['slug'] ?? null) ? $input['slug'] : null,
				parent: $parent,
				language: is_string($input['language'] ?? null) ? $input['language'] : null,
				redirect: ($input['redirect'] ?? false) === true
			);

			$revision = $input['revision'] ?? null;
			$updated  = $this->editor->update(Actor::admin($account), $entry, $edit, is_string($revision) ? $revision : null);
			$file     = $this->content->editable($updated);
		} catch (InvalidEdit $e) {
			return self::error($e->getMessage(), Status::BadRequest);
		} catch (EditRefused $e) {
			return self::refused($e);
		} catch (WriteException $e) {
			return self::error($e->getMessage(), Status::UnprocessableContent);
		}

		$this->remember($account, $updated);

		return self::json($this->describe($account, $updated, $file));
	}

	/**
	 * Returns the templates the editor's Template row offers on a type
	 * (D-783), as it reads them.
	 *
	 * @return list<array{name: string, label: string, description: string}>
	 */
	private function templatesFor(string $type): array
	{
		return $this->templates->for($type);
	}

	/**
	 * Refuses a `template` the editor wouldn't offer (D-783): it sets one
	 * name the active theme declares for the type. A name the file
	 * already has is never sent, since only changes are.
	 *
	 * @param array<mixed> $input
	 */
	private function templateRefused(array $input, string $type): ?ResponseInterface
	{
		$set = $input['set'] ?? null;

		if (! is_array($set) || ! array_key_exists('template', $set)) {
			return null;
		}

		$name = $set['template'];

		if (is_string($name) && $this->templates->offers($type, $name)) {
			return null;
		}

		return self::error(
			is_string($name) ? sprintf('The theme has no "%s" template for this type.', $name) : 'Choose one template by its name.',
			Status::UnprocessableContent,
			'template'
		);
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
	 * Copies an entry as a draft (D-275).
	 */
	public function duplicate(ServerRequestInterface $request, string $id): ResponseInterface
	{
		$account = self::account($request);
		$entry   = $this->content->find($id);

		if ($entry === null) {
			return self::error(sprintf('There\'s no entry with the id "%s".', $id), Status::NotFound);
		}

		try {
			$copy = $this->editor->duplicate(Actor::admin($account), $entry);
			$file = $this->content->editable($copy);
		} catch (EditRefused $e) {
			return self::refused($e);
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

		if (! $permanently) {
			try {
				[$trashed, $declared] = $this->editor->trash(Actor::admin($account), $entry, is_string($revision) ? $revision : null);
			} catch (EditRefused $e) {
				return self::refused($e);
			}

			// What an Undo sends to put it back as it was (D-525).
			return self::json(['trashed' => $id, 'restore' => [
				'status'   => $declared,
				'revision' => $trashed->version
			]]);
		}

		if ($entry->status !== EntryStatus::Trash) {
			return self::error(sprintf('"%s" isn\'t in the trash; move it there first.', $entry->title), Status::UnprocessableContent);
		}

		try {
			// Taken out of what links to it first (D-598), where the
			// account may edit, so nothing names it after.
			[$unlinked] = in_array($params['unlink'] ?? null, ['1', 'true'], true)
				? $this->referrers->unlink($entry, fn (Entry $referrer): bool => $this->permissions->can($account, ContentAction::Edit, $referrer))
				: [[]];

			$this->content->delete($entry);
		} catch (WriteConflict $e) {
			return self::error($e->getMessage(), Status::Conflict);
		} catch (WriteException $e) {
			return self::error($e->getMessage(), Status::UnprocessableContent);
		}

		return self::json(['deleted' => $id, 'unlinked' => count($unlinked)]);
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

		if ($entry === null) {
			return self::error('That isn\'t in the trash.', Status::NotFound);
		}

		if ($status === false) {
			return self::error('An entry is restored as "draft", "published", or `null` (no status, so published).', Status::UnprocessableContent);
		}

		try {
			$this->editor->restore(Actor::admin($account), $entry, $status, is_string($revision) ? $revision : null);
		} catch (EditRefused $e) {
			return self::refused($e);
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
				$this->editor->trash(Actor::admin($account), $entry, $revision);
			} else {
				// Publishing an undated entry dates it, which may rename
				// its file (D-519), the storage driver's to do.
				$this->editor->setStatus(Actor::admin($account), $entry, $action === 'publish' ? 'published' : 'draft', $revision);
			}
		} catch (EditRefused | WriteException $e) {
			return $e->getMessage();
		}

		return null;
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
		$list   = $this->archivePages->isList($entry);
		$target = $this->archivePages->isTarget($entry);
		$error  = ErrorPage::status($entry);
		$home   = $this->homepage->describe($entry);
		$fields = $this->types->formSchema($entry->type->name)->fields;
		$live   = $entry->status !== EntryStatus::Trash;

		// An index page describes the type's archive, not one of its
		// entries, so the type's fields don't apply; its title and status
		// do. With no date field, it can't be scheduled. A relation
		// archive's list page, and a page written for one target's archive
		// (D-602), introduce their pages the same way.
		if ($index || $list || $target) {
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
		// already uses stays, so it can still be edited. A few declared
		// fields are never edited here (`NOT_EDITED`).
		$fields = array_filter($fields, fn (Field $field): bool => $this->groups($field, $entry->type, $file->frontMatter) && ! in_array($field->name, self::NOT_EDITED, true));

		[$values, $extra] = self::split($fields, $file->frontMatter);

		// Declared keys the editor doesn't offer; a key nothing declares
		// isn't shown anywhere (D-786), though a save keeps it in the file.
		$schema = $this->types->schema($entry->type->name);
		$extra  = array_filter($extra, static fn (string $key): bool => $schema->field($key) !== null, ARRAY_FILTER_USE_KEY);

		// The body starts at its first line, without the blank lines after
		// the front matter; a save keeps those (D-334).

		return [
			'path'        => $entry->path,
			'id'          => $entry->id,
			'handle'      => $this->handles->of($entry),
			'slug'        => $entry->slug,
			'key'         => $entry->key,
			'language'    => $entry->language,
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
			'changedBy'   => $this->changedBy($entry),
			'index'       => $index,
			'archivePage' => $list,
			'archive'     => $this->archiveOf($entry),
			'introduces'  => $this->introduces($entry, $index),
			'errorPage'   => ErrorPage::status($entry),
			...$home,
			'templates'   => $live && ! $entry->landing && ! $list && ! $target && $error === null ? $this->templatesFor($entry->type->name) : [],
			'values'      => $values,
			'extra'       => $extra,
			'body'        => substr($file->body, strlen(DocumentEditor::gap($file->body))),
			'can'         => [
				'edit'         => $live && $this->permissions->can($account, ContentAction::Edit, $entry),
				'publish'      => $live && $this->permissions->can($account, ContentAction::Publish, $entry),
				'rename'       => $live && ! $entry->landing && ! $list && ! $target && $error === null,
				'language'     => $live && ! $entry->landing && ! $list && ! $target && $error === null && $this->app->languages->isMultilingual(),
				'move'         => $live && $entry->type instanceof Tree && ! $entry->landing && $error === null,
				'delete'       => ! $index && ! $target && $this->permissions->can($account, ContentAction::Delete, $entry),
				'duplicate'    => $live && ! $entry->landing && ! $list && ! $target && $error === null && $this->permissions->can($account, ContentAction::Create, $entry->type->name),
				'makeHomepage' => $live && $home['homeInstead'] !== null && $this->permissions->can($account, Capability::SiteSettings->value)
			],
			'violations'  => array_map(static fn (Violation $violation): array => [
				'field'    => $violation->field,
				'message'  => $violation->message,
				'severity' => $violation->severity->value,
				'kind'     => $violation->kind?->value
			], $this->linter->lintFile($entry->path))
		];
	}

	/**
	 * Describes the relation archive page an entry is (D-602, once D-353's
	 * credit fields), or `null`: the relation's `relation` name and plural
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
			'fields' => array_values(array_map(fn (Field $field): array => [...FormField::of($field, $this->types->controls($type->name), $this->defaults)->toArray(), ...$this->relationOf($type, $field)], $fields)),
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
				'entries'  => array_map(fn (Entry $source): array => [
					'id'     => $source->id,
					'title'  => $source->title,
					'status' => $source->status->value,
					'type'   => $source->type->labels->singular,
					'date'   => $source->published?->format('Y-m-d'),
					'image'  => $this->media->address($source->field('image'))
				], array_slice($entries, 0, $count <= self::LISTED_WHOLE ? self::LISTED_WHOLE : self::LISTED))
			];
		}

		usort($groups, static fn (array $a, array $b): int => [$b['count'], $a['key']] <=> [$a['count'], $b['key']]);

		return $groups;
	}

	/**
	 * Returns who made an entry's latest change when an app or a script
	 * made it (D-758), for the editor's note, or `null`: the app's name
	 * (`client`), the account's (`account`), and when (`time`).
	 *
	 * @return ?array{client: string, account: string, time: string}
	 */
	private function changedBy(Entry $entry): ?array
	{
		try {
			$latest = $entry->id === null ? null : $this->activity->latest($entry->id);
		} catch (ActivityException) {
			return null;
		}

		if ($latest === null || ! in_array($latest->via, [Via::Mcp, Via::Api], true)) {
			return null;
		}

		$by = $this->accounts->findById($latest->account);

		return [
			'client'  => $latest->client ?? $latest->via->label(),
			'account' => $by === null ? 'a removed account' : ($by->name ?? $by->username),
			'time'    => new DateTimeImmutable('@' . $latest->time)->format(DateTimeInterface::ATOM)
		];
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
	 * Returns the page a new tree's page goes under, by key, or `null`.
	 *
	 * @param  array<mixed> $input
	 * @throws InvalidEdit
	 */
	private static function parentOf(array $input): ?string
	{
		$parent = $input['parent'] ?? null;

		return $parent === null || is_string($parent) ? $parent : throw new InvalidEdit('"parent" must be the key of a page.');
	}

	/**
	 * Answers an edit the editor refused, with its field, and a full
	 * relation's target (D-608).
	 */
	private static function refused(EditRefused $refused): ResponseInterface
	{
		return self::json([
			'error' => $refused->getMessage(),
			...($refused->field === null ? [] : ['field' => $refused->field]),
			...$refused->details
		], $refused->refusal->status());
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
