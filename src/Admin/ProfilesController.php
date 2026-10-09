<?php

/**
 * Admin profiles controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use JsonException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Auth\Account;
use Blush\Auth\AccountStore;
use Blush\Auth\Accounts;
use Blush\Auth\AuthException;
use Blush\Auth\Capability;
use Blush\Auth\ContentAction;
use Blush\Auth\Permissions;
use Blush\Content\Entries;
use Blush\Content\Entry\Entry;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Http\RelatedController;
use Blush\Content\Relation\Relation;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\Profiles;
use Blush\Content\Writer\EntryChanges;
use Blush\Content\Writer\WriteException;
use Blush\Http\Response;
use Blush\Http\Status;

/**
 * Answers the profiles for linking accounts to them (D-356), for
 * accounts with `accounts.view` (D-362):
 *
 * - `GET profiles`: every profile, by name: `{"profiles": [{"slug",
 *   "title", "status", "account"` (the account linked to it, `{"username", "displayName"}`,
 *   or `null`), `"linkable"` (`false` when it's locked, D-605)`}]}`. A
 *   profile belongs to one account, so a picker offers only the
 *   unlocked ones without.
 *
 * And a profile's screen (D-353), for accounts that may edit the
 * profile (their own, or anyone's with the profiles type's
 * `edit.others`). A profile is its file (D-584):
 *
 * - `GET profiles/{slug}`: the `profile` (`{"slug", "title", "subtitle",
 *   "avatar", "status", "path", "id", "handle", "url", "uses", "linkable"}`), where it `appears` (each credit
 *   relation from each type that credits people, D-602: `{"type", "typeLabel", "relation",
 *   "label", "entries"` (published entries crediting them there),
 *   `"archive"` (the archive's address, or `null` without one), `"page"`
 *   (the page written for it, `{"path", "id", "handle", "title", "status"}`, or
 *   `null`; kept, and unreachable, while the relation has no archive)`}`), whether an account is `linked`, and, for whoever
 *   manages accounts, the `account` (as `PeopleJson::account()` has it).
 * - `PATCH profiles/{slug}` (`{"linkable": bool}`): locks the profile
 *   against being linked to an account (`linkable: false` in its front
 *   matter), or unlocks it (the key removed), D-605. Needs
 *   `accounts.edit` as well; answers `{"linkable"}`.
 * - `POST profiles/{slug}/pages` (`{"type", "relation"}`): writes the page
 *   for the profile's archive under a credit relation (`_cooks/jane` in the type's
 *   folder), a draft titled with the profile's name, and answers `201`
 *   with its `{"id", "handle"}`. Needs to create entries of that type.
 * - `DELETE profiles/{slug}/pages/{type}/{relation}`: moves that page to the
 *   trash (D-370), so the archive shows the profile's body again. Needs
 *   to delete the page.
 */
final readonly class ProfilesController
{
	public function __construct(
		private Entries $content,
		private ContentTypes $types,
		private ContentUrls $urls,
		private EntryHandles $handles,
		private Permissions $permissions,
		private AccountStore $accounts,
		private PeopleJson $json,
		private Accounts $names
	) {}

	public function index(ServerRequestInterface $request): ResponseInterface
	{
		$viewer   = $request->getAttribute(Account::class);
		$profiles = $this->types->profiles();

		if (! $viewer instanceof Account || ! $this->permissions->can($viewer, Capability::AccountsView)) {
			return self::json(['error' => 'You aren\'t allowed to see accounts.'], Status::Forbidden);
		}

		if ($profiles === null) {
			return self::json(['profiles' => []]);
		}

		try {
			$accounts = $this->accounts->all();
		} catch (AuthException $error) {
			return self::json(['error' => $error->getMessage()], Status::InternalServerError);
		}

		$linked = [];

		foreach ($accounts as $account) {
			if ($account->author !== null) {
				$linked[$account->author] ??= ['username' => $account->username, 'displayName' => $this->names->displayName($account)];
			}
		}

		$listed = [];

		foreach ($this->content->query()->any()->type($profiles->name)->limit(null)->get() as $entry) {
			$listed[$entry->key] = ['slug' => $entry->key, 'title' => $entry->title !== '' ? $entry->title : $entry->key, 'status' => $entry->status->value, 'account' => $linked[$entry->key] ?? null, 'linkable' => $entry->field('linkable') !== false];
		}

		$listed = array_values($listed);
		usort($listed, static fn (array $a, array $b): int => strnatcasecmp($a['title'], $b['title']));

		return self::json(['profiles' => $listed]);
	}

	public function show(ServerRequestInterface $request, string $slug): ResponseInterface
	{
		$found = $this->find($request, $slug);

		if ($found instanceof ResponseInterface) {
			return $found;
		}

		[$viewer, $profiles, $profile] = $found;

		$account = $this->linkedAccount($profile->slug);
		$manages = $this->permissions->can($viewer, Capability::AccountsView);

		return self::json([
			'profile' => [
				'slug'     => $profile->slug,
				'title'    => $profile->title,
				'subtitle' => self::text($profile->field('subtitle')),
				'avatar'   => self::text($profile->field('avatar')),
				'status'   => $profile->status->value,
				'path'     => $profile->path,
				'id'       => $profile->id,
				'type'     => $profiles->name,
				'handle'   => $this->handles->of($profile),
				'url'      => $this->urls->profile($profile->slug),
				'uses'     => $this->content->termCounts($profiles->name)[$profile->slug] ?? 0,
				'linkable' => $profile->field('linkable') !== false
			],
			'appears' => $this->appears($profiles, $profile),
			'linked'  => $account !== null,
			'account' => $account !== null && $manages ? $this->json->account($account, $viewer) : null
		]);
	}

	public function lock(ServerRequestInterface $request, string $slug): ResponseInterface
	{
		$found = $this->find($request, $slug);

		if ($found instanceof ResponseInterface) {
			return $found;
		}

		[$viewer, , $profile] = $found;

		if (! $this->permissions->can($viewer, Capability::AccountsView) || ! $this->permissions->can($viewer, Capability::AccountsEdit)) {
			return self::json(['error' => 'You aren\'t allowed to change accounts\' profiles.'], Status::Forbidden);
		}

		try {
			$input = json_decode((string) $request->getBody(), true, 4, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			$input = null;
		}

		if (! is_array($input) || ! is_bool($input['linkable'] ?? null)) {
			return self::json(['error' => 'Send the JSON "linkable", true or false.'], Status::UnprocessableContent);
		}

		$linkable = $input['linkable'];

		if ($linkable !== ($profile->field('linkable') !== false)) {
			try {
				$this->content->change($profile, $linkable ? new EntryChanges(remove: ['linkable']) : new EntryChanges(set: ['linkable' => false]));
			} catch (WriteException $error) {
				return self::json(['error' => $error->getMessage()], Status::Conflict);
			}
		}

		return self::json(['linkable' => $linkable]);
	}

	public function write(ServerRequestInterface $request, string $slug): ResponseInterface
	{
		$found = $this->find($request, $slug);

		if ($found instanceof ResponseInterface) {
			return $found;
		}

		[$viewer, , $profile] = $found;

		try {
			$input = json_decode((string) $request->getBody(), true, 4, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			$input = null;
		}

		$place = is_array($input) && is_string($input['type'] ?? null) && is_string($input['relation'] ?? null) ? $this->place($input['type'], $input['relation']) : null;

		if ($place === null) {
			return self::json(['error' => 'Send the JSON "type" and "relation" of a credit with archives.'], Status::UnprocessableContent);
		}

		[$type, $relation] = $place;

		if (! $this->permissions->can($viewer, ContentAction::Create, $type->name)) {
			return self::json(['error' => sprintf('You aren\'t allowed to create %s.', $type->labels->items)], Status::Forbidden);
		}

		try {
			$entry = $this->content->createAt($type, RelatedController::word($relation) . "/{$profile->slug}", new EntryChanges(set: ['title' => $profile->title, 'status' => 'draft'], body: "\n"));
		} catch (WriteException $error) {
			return self::json(['error' => $error->getMessage()], Status::Conflict);
		}

		return self::json(['id' => $entry->id, 'type' => $type->name, 'handle' => $this->handles->of($entry)], Status::Created);
	}

	public function remove(ServerRequestInterface $request, string $slug, string $type, string $relation): ResponseInterface
	{
		$found = $this->find($request, $slug);

		if ($found instanceof ResponseInterface) {
			return $found;
		}

		[$viewer, , $profile] = $found;

		$place = $this->place($type, $relation, archive: false);
		$page  = $place === null ? null : $this->content->named($place[0]->name, RelatedController::word($place[1]) . "/{$profile->slug}");

		if ($page === null) {
			return self::json(['error' => 'There\'s no page written for that archive.'], Status::NotFound);
		}

		if (! $this->permissions->can($viewer, ContentAction::Delete, $page)) {
			return self::json(['error' => 'You aren\'t allowed to delete that page.'], Status::Forbidden);
		}

		try {
			$this->content->delete($page);
		} catch (WriteException $error) {
			return self::json(['error' => $error->getMessage()], Status::Conflict);
		}

		return self::json(['removed' => $page->path]);
	}

	/**
	 * Returns the viewer, the profiles type, and the profile, or the
	 * response that refuses them.
	 *
	 * @return array{Account, Profiles, Entry}|ResponseInterface
	 */
	private function find(ServerRequestInterface $request, string $slug): array|ResponseInterface
	{
		$viewer = $request->getAttribute(Account::class);

		if (! $viewer instanceof Account) {
			return self::json(['error' => 'Sign in first.'], Status::Unauthorized);
		}

		$profiles = $this->types->profiles();
		$profile  = $profiles === null ? null : $this->content->term($profiles->name, $slug);

		if ($profiles === null || $profile === null) {
			return self::json(['error' => sprintf('There\'s no profile "%s".', $slug)], Status::NotFound);
		}

		return $this->permissions->can($viewer, ContentAction::Edit, $profile) ? [$viewer, $profiles, $profile] : self::json(['error' => 'You aren\'t allowed to edit that profile.'], Status::Forbidden);
	}

	/**
	 * Describes where a profile appears: each credit relation from each
	 * type that credits people (D-602), by type label, then relation name.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function appears(Profiles $profiles, Entry $profile): array
	{
		$types = $this->types->crediting();
		$rows  = [];

		uasort($types, static fn (ContentType $a, ContentType $b): int => strnatcasecmp($a->labels->plural, $b->labels->plural));

		foreach ($types as $type) {
			$credits = $this->types->credits($type->name);

			ksort($credits);

			foreach ($credits as $relation) {
				$page   = $type->folder === '' ? null : $this->content->named($type->name, RelatedController::word($relation) . "/{$profile->slug}");
				$rows[] = [
					'type'      => $type->name,
					'typeLabel' => $type->labels->plural,
					'relation'  => $relation->name,
					'label'     => $relation->label === '' ? ucfirst(str_replace('_', ' ', $relation->name)) : $relation->label,
					'entries'   => $this->content->query()->type($type->name)->whereTerm((string) $relation->termKey(), $profile->slug)->count(),
					'archive'   => $this->urls->related($type, $relation, $profile->slug),
					'page'      => $page === null ? null : ['path' => $page->path, 'id' => $page->id, 'type' => $page->type->name, 'handle' => $this->handles->of($page), 'title' => $page->title, 'status' => $page->status->value]
				];
			}
		}

		return $rows;
	}

	/**
	 * Returns a type and its credit relation when the relation has
	 * archives under it, which is what writing a page needs (removing one
	 * doesn't, since a page outlives its archive being turned off).
	 *
	 * @return ?array{ContentType, Relation}
	 */
	private function place(string $type, string $relation, bool $archive = true): ?array
	{
		$contentType = $this->types->find($type);
		$credit      = $contentType === null ? null : $this->types->credits($contentType->name)[$relation] ?? null;

		return $contentType !== null && $credit !== null && $contentType->folder !== '' && (! $archive || isset($this->types->relationArchives($contentType)[$relation])) ? [$contentType, $credit] : null;
	}

	/**
	 * Returns the first account linked to a profile, or `null`.
	 */
	private function linkedAccount(string $slug): ?Account
	{
		try {
			return array_find($this->accounts->all(), static fn (Account $account): bool => $account->author === $slug);
		} catch (AuthException) {
			return null;
		}
	}

	private static function text(mixed $value): ?string
	{
		return is_string($value) && $value !== '' ? $value : null;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private static function json(array $data, Status $status = Status::Ok): ResponseInterface
	{
		return Response::json($data, $status, ['Cache-Control' => 'no-store']);
	}
}
