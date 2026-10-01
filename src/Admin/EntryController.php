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
use Blush\Auth\Capability;
use Blush\Auth\Permissions;
use Blush\Content\ContentRepository;
use Blush\Content\Entry\Entry;
use Blush\Content\Lint\Linter;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Status as EntryStatus;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\DateArchives;
use Blush\Content\Type\Taxonomy;
use Blush\Content\Writer\ContentWriter;
use Blush\Content\Writer\DocumentEditor;
use Blush\Content\Writer\EditableEntry;
use Blush\Content\Writer\EntryChanges;
use Blush\Content\Writer\WriteConflict;
use Blush\Content\Writer\WriteException;
use Blush\Core\AppConfig;
use Blush\Field\Field;
use Blush\Field\FieldSet;
use Blush\Field\Violation;
use Blush\Http\Response;
use Blush\Http\Status;
use Blush\Support\Slug;

/**
 * The admin's editing API (D-229), over `ContentWriter`:
 *
 * - `GET    entries/{id}`: an entry for editing: its field values by name
 *   (from whichever key or alias the file uses), other front matter,
 *   body, revision, when the file was last written (`modified`), type
 *   and field descriptions, what the account may do, and the file's
 *   problems, and its handle (`EntryHandles`, D-253), or `null`.
 * - `GET    content/{type}/{key}`: the same, found by handle.
 * - `GET    entries/new?type=…`: a new entry of a type, described the same
 *   way but not yet written (no `id`, `handle`, or `revision`), so the
 *   editor opens on it and its first save creates it (D-336). It's a
 *   draft crediting the account's author, with nothing else filled in.
 * - `POST   entries`: creates one (`type`, `title`, optional `slug`,
 *   `set`, `body`, `status`). New entries are drafts unless asked
 *   otherwise, and credit the account's author.
 * - `PATCH  entries/{id}`: changes one (`revision` required; `set`,
 *   `remove`, `body`, `status`, `published`, `slug`, `redirect`). A new
 *   `slug` renames it (D-277): its `slug` key when the file has one,
 *   else the file, first, so a slug that's refused (not a slug, another
 *   entry's, or a landing page's) changes nothing; the refusal is a 422
 *   with `field: "slug"`. With `redirect: true`, a published entry's old
 *   address is added to its `redirect_from`.
 * - `DELETE entries/{id}?revision=…`: moves it to the trash.
 * - `POST   entries/bulk`: publishes, moves to draft, or trashes several
 *   (`action`: `publish`, `draft`, or `trash`; `ids`, at most 100) at
 *   their current revisions, since a status change or a move to the
 *   trash loses no one's writing (D-301). Each entry is checked as alone:
 *   one that can't be changed (not allowed, an index page to trash, a
 *   required field empty for publishing, a write that fails) is skipped
 *   with the reason, and the rest go ahead. Answers `done` (ids) and
 *   `skipped` (`id`, `title`, `reason`).
 * - `POST   entries/{id}/duplicate`: copies it beside itself as a draft
 *   titled "… (Copy)", slugged `{slug}-copy`, with the same authors,
 *   dated now if its type is dated (D-275). Needs `content.create`, and `content.edit` for the
 *   entry; a landing page (an index page or the home page) can't be
 *   copied.
 *
 * **Permissions come from the change, not only the entry:** editing
 * needs `content.edit` for the entry (with D-219's ownership and
 * live-entry rules); a result that isn't a draft needs
 * `content.publish`; changing the authors so the account's own author
 * isn't among them needs `content.edit.others`; creating needs
 * `content.create`; deleting needs `content.delete` for the entry.
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

	public function __construct(
		private ContentWriter $writer,
		private ContentRepository $content,
		private ContentTypes $types,
		private ContentUrls $urls,
		private Linter $linter,
		private EntryHandles $handles,
		private Permissions $permissions,
		private AppConfig $app,
		private ClockInterface $clock
	) {}

	/**
	 * Answers an entry for editing.
	 */
	public function show(ServerRequestInterface $request, string $id): ResponseInterface
	{
		$entry = $this->content->find($id);

		if ($entry === null) {
			return self::error(sprintf('There\'s no "%s" entry.', $id), Status::NotFound);
		}

		return $this->edit($request, $entry);
	}

	/**
	 * Answers an entry for editing, found by its handle (D-253).
	 */
	public function named(ServerRequestInterface $request, string $type, string $key): ResponseInterface
	{
		$entry = $this->handles->find($type, $key);

		if ($entry === null || $entry->source === null) {
			return self::error(sprintf('There\'s no "%s" entry at "%s".', $type, $key), Status::NotFound);
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

		if (! $this->permissions->can($account, Capability::ContentCreate)) {
			return self::error('You aren\'t allowed to create entries.', Status::Forbidden);
		}

		if ($type === null) {
			return self::error('Send a known "type".', Status::BadRequest);
		}

		$fields = array_filter($this->types->schema($type->name)->fields, fn (Field $field): bool => $this->groups($field, $type, []));

		return self::json([
			'id'          => null,
			'handle'      => null,
			'slug'        => '',
			'revision'    => null,
			'modified'    => null,
			'title'       => '',
			'status'      => EntryStatus::Draft->value,
			'own'         => true,
			'url'         => null,
			'type'        => $this->typeOf($type, $fields),
			'index'       => false,
			'authorsPage' => false,
			'values'      => $this->authorDefault($account, $type->name),
			'extra'       => [],
			'body'        => '',
			'can'         => [
				'edit'      => true,
				'publish'   => $this->permissions->can($account, Capability::ContentPublish),
				'rename'    => true,
				'delete'    => false,
				'duplicate' => false
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

		if (! $this->permissions->can($account, Capability::ContentEdit, $entry)) {
			return self::error('You aren\'t allowed to edit that entry.', Status::Forbidden);
		}

		return self::json($this->describe($account, $entry, $this->writer->load($entry->id)));
	}

	/**
	 * Creates an entry.
	 */
	public function create(ServerRequestInterface $request): ResponseInterface
	{
		$account = self::account($request);
		$input   = self::input($request);

		if (! $this->permissions->can($account, Capability::ContentCreate)) {
			return self::error('You aren\'t allowed to create entries.', Status::Forbidden);
		}

		$type  = is_string($input['type'] ?? null) ? $this->types->find($input['type']) : null;
		$title = $input['title'] ?? null;

		if ($type === null || ! is_string($title) || trim($title) === '') {
			return self::error('Send a known "type" and a "title".', Status::BadRequest);
		}

		$slug = is_string($input['slug'] ?? null) && $input['slug'] !== '' ? $input['slug'] : Slug::from($title);
		$now  = $this->clock->now()->setTimezone($this->app->timezone());

		try {
			$status = self::stringOr($input, 'status', 'draft');
			$set    = [
				'title' => $title,
				...($type->dateArchives === DateArchives::None ? [] : ['published' => self::dateString($now)]),
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

		if (! self::isDraft($changes, null) && ! $this->permissions->can($account, Capability::ContentPublish)) {
			return self::error('You aren\'t allowed to publish, so new entries must be drafts.', Status::Forbidden);
		}

		try {
			$result = $this->writer->create($type, $slug, $changes, $now);
		} catch (WriteException $e) {
			return self::error($e->getMessage(), Status::UnprocessableContent);
		}

		$entry = $this->content->find($result->id);

		return $entry === null
			? self::error('The entry was written but couldn\'t be read back; check content health.', Status::UnprocessableContent)
			: self::json($this->describe($account, $entry, $this->writer->load($result->id)), Status::Created);
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
			return self::error(sprintf('There\'s no "%s" entry.', $id), Status::NotFound);
		}

		if (! $this->permissions->can($account, Capability::ContentEdit, $entry)) {
			return self::error('You aren\'t allowed to edit that entry.', Status::Forbidden);
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

		$rename  = is_string($slug) && $slug !== '' && $slug !== $entry->slug ? $slug : null;
		$problem = $rename === null ? null : $this->slugProblem($entry, $rename);

		if ($problem !== null) {
			return self::error($problem, Status::UnprocessableContent, 'slug');
		}

		// A `slug` key names the entry, so it's what changes; and the old
		// address can redirect to the new one.
		if ($rename !== null) {
			$file    = $this->writer->load($id);
			$changes = $this->withRedirect($changes, $entry, $file, ($input['redirect'] ?? false) === true);

			if (self::has($file, $this->keys($entry->type->name, 'slug'))) {
				$changes = new EntryChanges([...$changes->set, 'slug' => $rename], $changes->remove, $changes->body);
				$rename  = null;
			}
		}

		try {
			// Renaming the file first, so a refused name leaves it as it was.
			if ($rename !== null) {
				$renamed  = $this->writer->rename($id, $rename, $revision);
				$id       = $renamed->id;
				$revision = $renamed->revision ?? $revision;
			}

			$result = $this->writer->update($id, $changes, $revision);
		} catch (WriteConflict $e) {
			return self::error($e->getMessage(), Status::Conflict);
		} catch (WriteException $e) {
			return self::error($e->getMessage(), Status::UnprocessableContent);
		}

		$updated = $this->content->find($result->id);

		return $updated === null
			? self::error('The entry was saved but couldn\'t be read back; check content health.', Status::UnprocessableContent)
			: self::json($this->describe($account, $updated, $this->writer->load($result->id)));
	}

	/**
	 * Copies an entry as a draft (D-275).
	 */
	public function duplicate(ServerRequestInterface $request, string $id): ResponseInterface
	{
		$account = self::account($request);
		$entry   = $this->content->find($id);

		if ($entry === null) {
			return self::error(sprintf('There\'s no "%s" entry.', $id), Status::NotFound);
		}

		if (! $this->permissions->can($account, Capability::ContentCreate) || ! $this->permissions->can($account, Capability::ContentEdit, $entry)) {
			return self::error('You aren\'t allowed to duplicate that entry.', Status::Forbidden);
		}

		if (IndexPage::is($entry)) {
			return self::error(sprintf('"%s" is the index page for %s, and there\'s only one.', $entry->title, $entry->type->labels->items), Status::UnprocessableContent);
		}

		$now   = $this->clock->now()->setTimezone($this->app->timezone());
		$title = sprintf('%s (Copy)', trim($entry->title) === '' ? 'Untitled' : $entry->title);
		$set   = [
			'title'  => $title,
			'status' => 'draft',
			...($entry->type->dateArchives === DateArchives::None ? [] : ['published' => self::dateString($now)])
		];

		try {
			// The copy's name is its file's, and the original's old
			// addresses stay the original's.
			$result = $this->writer->duplicate($id, Slug::from(($entry->slug === '' ? $title : $entry->slug) . '-copy'), new EntryChanges(set: $set, remove: ['slug', 'redirect_from']), $now);
		} catch (WriteException $e) {
			return self::error($e->getMessage(), Status::UnprocessableContent);
		}

		$copy = $this->content->find($result->id);

		return $copy === null
			? self::error('The copy was written but couldn\'t be read back; check content health.', Status::UnprocessableContent)
			: self::json($this->describe($account, $copy, $this->writer->load($result->id)), Status::Created);
	}

	/**
	 * Deletes an entry (to the trash).
	 */
	public function delete(ServerRequestInterface $request, string $id): ResponseInterface
	{
		$account  = self::account($request);
		$entry    = $this->content->find($id);
		$revision = $request->getQueryParams()['revision'] ?? null;

		if ($entry === null) {
			return self::error(sprintf('There\'s no "%s" entry.', $id), Status::NotFound);
		}

		if (! $this->permissions->can($account, Capability::ContentDelete, $entry)) {
			return self::error('You aren\'t allowed to delete that entry.', Status::Forbidden);
		}

		if (IndexPage::is($entry)) {
			return self::error(sprintf('"%s" is the index page for %s, so it can\'t be moved to the trash.', $entry->title, $entry->type->labels->items), Status::UnprocessableContent);
		}

		if (! is_string($revision)) {
			return self::error('Send the "revision" you loaded, so no one else\'s change is lost.', Status::PreconditionRequired);
		}

		try {
			$this->writer->delete($id, $revision);
		} catch (WriteConflict $e) {
			return self::error($e->getMessage(), Status::Conflict);
		} catch (WriteException $e) {
			return self::error($e->getMessage(), Status::UnprocessableContent);
		}

		return self::json(['deleted' => $id]);
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
		if ($action === 'trash') {
			if (! $this->permissions->can($account, Capability::ContentDelete, $entry)) {
				return 'You aren\'t allowed to delete it.';
			}

			if (IndexPage::is($entry)) {
				return 'It\'s an index page, so it can\'t be moved to the trash.';
			}
		} elseif (! $this->permissions->can($account, Capability::ContentEdit, $entry)) {
			return 'You aren\'t allowed to edit it.';
		}

		try {
			$revision = $this->writer->load($entry->id)->revision;

			if ($action === 'trash') {
				$this->writer->delete($entry->id, $revision);

				return null;
			}

			$changes = $this->withStatus(new EntryChanges(), $action === 'publish' ? 'published' : 'draft', [], $entry);
			$refusal = $this->refusal($account, $entry, $changes) ?? ($action === 'publish' ? $this->missing($entry) : null);

			if ($refusal !== null) {
				return $refusal;
			}

			$this->writer->update($entry->id, $changes, $revision);
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
	 * Whether a file's front matter has any of a field's keys.
	 *
	 * @param list<string> $keys
	 */
	private static function has(EditableEntry $file, array $keys): bool
	{
		return array_any($keys, static fn (string $key): bool => array_key_exists($key, $file->frontMatter));
	}

	/**
	 * Returns what's wrong with renaming an entry to a slug, or `null`.
	 */
	private function slugProblem(Entry $entry, string $slug): ?string
	{
		if ($entry->landing) {
			return sprintf('"%s" is its folder\'s landing page, so its slug is the folder\'s.', $entry->title);
		}

		if (! Slug::isSlug($slug)) {
			return sprintf('Slugs are lowercase letters, numbers, and hyphens; try "%s".', Slug::from($slug) ?: 'untitled');
		}

		$folder = dirname($entry->key);
		$key    = ($folder === '.' ? '' : "{$folder}/") . $slug;

		return $this->content->named($entry->type->name, $key) === null
			? null
			: sprintf('Another %s already has the slug "%s".', $entry->type->labels->item, $slug);
	}

	/**
	 * Returns why the account may not make a change, or `null`.
	 */
	private function refusal(Account $account, Entry $entry, EntryChanges $changes): ?string
	{
		if (! self::isDraft($changes, $entry) && ! $this->permissions->can($account, Capability::ContentPublish, $entry)) {
			return 'You aren\'t allowed to publish that entry; keep it a draft.';
		}

		$field = $this->authorField();

		if ($field === null || $this->permissions->can($account, Capability::ContentEditOthers, $entry)) {
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
	 * Returns the account's author for a new entry's authors, when the
	 * type supports authors (D-329).
	 *
	 * @return array<string, list<string>>
	 */
	private function authorDefault(Account $account, string $type): array
	{
		$field = $this->authorField();

		return $field === null || $account->author === null || ! $this->types->get($type)->authors
			? []
			: [$field => [$account->author]];
	}

	/**
	 * Returns the field entries credit authors through (`authors`), or
	 * `null` when the site has no authors type.
	 */
	private function authorField(): ?string
	{
		return $this->types->authors()?->field;
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
	 * taxonomy's term field, and a term field when its taxonomy groups the
	 * type or the file uses it.
	 *
	 * @param array<mixed> $frontMatter
	 */
	private function groups(Field $field, ContentType $type, array $frontMatter): bool
	{
		foreach ($this->types->taxonomies() as $taxonomy) {
			$term = $taxonomy->termField();

			if ($term->name !== $field->name) {
				continue;
			}

			foreach ([$term->name, ...$term->aliases] as $key) {
				if (array_key_exists($key, $frontMatter)) {
					return true;
				}
			}

			return $taxonomy->types === []
				? ! $type->hasTerms()
				: in_array($type->name, $taxonomy->types, true);
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
		$people = AuthorsPage::is($entry);
		$fields = $this->types->schema($entry->type->name)->fields;

		// An index page describes the type's archive, not one of its
		// entries, so the type's fields don't apply; its title and status
		// do. With no date field, it can't be scheduled. A type's authors
		// page (D-329) introduces its authors list the same way.
		if ($index || $people) {
			$fields = array_filter($fields, static fn (Field $field): bool => in_array($field->name, ['title', 'status'], true));
		}

		// The schema has every taxonomy's term field, so a file may use any
		// of them, but the editor offers only the taxonomies that group the
		// type (D-283): those naming it in `types`, and those with no
		// `types` (every type) unless it's a taxonomy itself. One the file
		// already uses stays, so it can still be edited.
		$fields = array_filter($fields, fn (Field $field): bool => $this->groups($field, $entry->type, $file->frontMatter));

		[$values, $extra] = self::split($fields, $file->frontMatter);

		// The body starts at its first line, without the blank lines after
		// the front matter; a save keeps those (D-334).

		return [
			'id'          => $file->id,
			'handle'      => $this->handles->of($entry),
			'slug'        => $entry->slug,
			'revision'    => $file->revision,
			'modified'    => $file->modified === null ? null : new DateTimeImmutable('@' . $file->modified)->format(DateTimeInterface::ATOM),
			'title'       => $entry->title,
			'status'      => $entry->status->value,
			'own'         => $this->permissions->owns($account, $entry),
			'url'         => $entry->isPublished() ? $this->urls->entry($entry) : null,
			'type'        => $this->typeOf($entry->type, $fields),
			'index'       => $index,
			'authorsPage' => $people,
			'values'      => $values,
			'extra'       => $extra,
			'body'        => substr($file->body, strlen(DocumentEditor::gap($file->body))),
			'can'         => [
				'edit'      => $this->permissions->can($account, Capability::ContentEdit, $entry),
				'publish'   => $this->permissions->can($account, Capability::ContentPublish, $entry),
				'rename'    => ! $entry->landing && ! $people,
				'delete'    => ! $index && $this->permissions->can($account, Capability::ContentDelete, $entry),
				'duplicate' => ! $entry->landing && ! $people && $this->permissions->can($account, Capability::ContentCreate)
			],
			'violations'  => array_map(static fn (Violation $violation): array => [
				'field'    => $violation->field,
				'message'  => $violation->message,
				'severity' => $violation->severity->value
			], $this->linter->lintFile($file->id))
		];
	}

	/**
	 * Describes a type for the editor: its name, kind, whether it's
	 * dated, the fields it edits (as forms take them), and the field sets
	 * attached to it (D-337), each with its `name`, `label`,
	 * `description`, and the names of its `fields`, so the editor groups
	 * them.
	 *
	 * @param  array<array-key, Field> $fields
	 * @return array<string, mixed>
	 */
	private function typeOf(ContentType $type, array $fields): array
	{
		return [
			'name'   => $type->name,
			'kind'   => $type->kind()->value,
			'dated'  => $type->dateArchives !== DateArchives::None,
			'fields' => array_values(array_map(static fn (Field $field): array => $field->toForm(), $fields)),
			'sets'   => array_map(static fn (FieldSet $set): array => [
				'name'        => $set->name,
				'label'       => $set->label,
				'description' => $set->description,
				'fields'      => array_keys($set->schema->fields)
			], $this->types->setsFor($type->name))
		];
	}

	/**
	 * Splits front matter into field values by name (from the first of a
	 * field's name and aliases the file uses) and everything else.
	 *
	 * @param  array<array-key, Field> $fields
	 * @param  array<array-key, mixed> $frontMatter
	 * @return array{array<string, mixed>, array<string, mixed>}
	 */
	private static function split(array $fields, array $frontMatter): array
	{
		$values  = [];
		$claimed = [];

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
