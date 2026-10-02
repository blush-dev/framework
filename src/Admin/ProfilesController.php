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
use Blush\Content\ContentRepository;
use Blush\Content\Entry\Entry;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\PeopleField;
use Blush\Content\Type\Profiles;
use Blush\Content\Writer\ContentWriter;
use Blush\Content\Writer\EntryChanges;
use Blush\Content\Writer\WriteException;
use Blush\Http\Response;
use Blush\Http\Status;

/**
 * Answers the profiles for linking accounts to them (D-356), for
 * accounts with `accounts.view` (D-362):
 *
 * - `GET profiles`: every profile, by name: `{"profiles": [{"slug",
 *   "title", "status"` (`null` for one credited without a file),
 *   `"account"` (the account linked to it, `{"username", "displayName"}`,
 *   or `null`)`}]}`. A profile belongs to one account, so a picker
 *   offers only the ones without.
 *
 * And a profile's screen (D-353), for accounts that may edit the
 * profile (their own, or anyone's with the profiles type's `edit.others`; a profile
 * with no file needs the latter):
 *
 * - `GET profiles/{slug}`: the `profile` (`{"slug", "title", "subtitle",
 *   "avatar", "status"` (`null` without a file), `"virtual", "id",
 *   "handle", "url", "uses"}`), where it `appears` (each people field of
 *   each type that credits people: `{"type", "typeLabel", "field",
 *   "label", "entries"` (published entries crediting them there),
 *   `"archive"` (the archive's address, or `null` without one), `"page"`
 *   (the page written for it, `{"id", "handle", "title", "status"}`, or
 *   `null`)`}`), whether an account is `linked`, and, for whoever
 *   manages accounts, the `account` (as `PeopleJson::account()` has it).
 * - `POST profiles/{slug}/pages` (`{"type", "field"}`): writes the page
 *   for the profile's archive under a field (`_cooks/jane` in the type's
 *   folder), a draft titled with the profile's name, and answers `201`
 *   with its `{"id", "handle"}`. Needs to create entries of that type.
 * - `DELETE profiles/{slug}/pages/{type}/{field}`: moves that page to the
 *   trash, so the archive shows the profile's bio again. Needs
 *   to delete the page.
 */
final readonly class ProfilesController
{
	public function __construct(
		private ContentRepository $content,
		private ContentTypes $types,
		private ContentUrls $urls,
		private ContentWriter $writer,
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
			$listed[$entry->key] = ['slug' => $entry->key, 'title' => $entry->title !== '' ? $entry->title : $entry->key, 'status' => $entry->status->value, 'account' => $linked[$entry->key] ?? null];
		}

		foreach (array_keys($this->content->termCounts($profiles->name)) as $slug) {
			$slug = (string) $slug;
			$term = isset($listed[$slug]) ? null : $this->content->term($profiles->name, $slug);

			if ($term !== null) {
				$listed[$slug] = ['slug' => $slug, 'title' => $term->title, 'status' => null, 'account' => $linked[$slug] ?? null];
			}
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
				'status'   => $profile->isVirtual() ? null : $profile->status->value,
				'virtual'  => $profile->isVirtual(),
				'id'       => $profile->isVirtual() ? null : $profile->id,
				'handle'   => $profile->isVirtual() ? null : $this->handles->of($profile),
				'url'      => $this->urls->profile($profile->slug),
				'uses'     => $this->content->termCounts($profiles->name)[$profile->slug] ?? 0
			],
			'appears' => $this->appears($profiles, $profile),
			'linked'  => $account !== null,
			'account' => $account !== null && $manages ? $this->json->account($account, $viewer) : null
		]);
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

		$place = is_array($input) && is_string($input['type'] ?? null) && is_string($input['field'] ?? null) ? $this->place($input['type'], $input['field']) : null;

		if ($place === null) {
			return self::json(['error' => 'Send the JSON "type" and "field" of a people field with archives.'], Status::UnprocessableContent);
		}

		[$type, $field] = $place;

		if (! $this->permissions->can($viewer, ContentAction::Create, $type->name)) {
			return self::json(['error' => sprintf('You aren\'t allowed to create %s.', $type->labels->items)], Status::Forbidden);
		}

		try {
			$result = $this->writer->createAt($type, $field->personPage($profile->slug), new EntryChanges(set: ['title' => $profile->title, 'status' => 'draft'], body: "\n"));
		} catch (WriteException $error) {
			return self::json(['error' => $error->getMessage()], Status::Conflict);
		}

		$entry = $this->content->find($result->id);

		return self::json(['id' => $result->id, 'handle' => $entry === null ? null : $this->handles->of($entry)], Status::Created);
	}

	public function remove(ServerRequestInterface $request, string $slug, string $type, string $field): ResponseInterface
	{
		$found = $this->find($request, $slug);

		if ($found instanceof ResponseInterface) {
			return $found;
		}

		[$viewer, , $profile] = $found;

		$place = $this->place($type, $field);
		$page  = $place === null ? null : $this->content->named($place[0]->name, $place[1]->personPage($profile->slug));

		if ($page === null) {
			return self::json(['error' => 'There\'s no page written for that archive.'], Status::NotFound);
		}

		if (! $this->permissions->can($viewer, ContentAction::Delete, $page)) {
			return self::json(['error' => 'You aren\'t allowed to delete that page.'], Status::Forbidden);
		}

		try {
			$this->writer->delete($page->id);
		} catch (WriteException $error) {
			return self::json(['error' => $error->getMessage()], Status::Conflict);
		}

		return self::json(['removed' => $page->id]);
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

		$allowed = $profile->isVirtual()
			? $this->permissions->can($viewer, ContentAction::EditOthers, $profiles->name)
			: $this->permissions->can($viewer, ContentAction::Edit, $profile);

		return $allowed ? [$viewer, $profiles, $profile] : self::json(['error' => 'You aren\'t allowed to edit that profile.'], Status::Forbidden);
	}

	/**
	 * Describes where a profile appears: each people field of each type
	 * that credits people, by type label, then the field's order.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function appears(Profiles $profiles, Entry $profile): array
	{
		$types = $this->types->crediting();
		$rows  = [];

		uasort($types, static fn (ContentType $a, ContentType $b): int => strnatcasecmp($a->labels->plural, $b->labels->plural));

		foreach ($types as $type) {
			foreach ($type->people as $field) {
				$page   = $this->urls->hasArchive($type, $field) ? $this->content->named($type->name, $field->personPage($profile->slug)) : null;
				$rows[] = [
					'type'      => $type->name,
					'typeLabel' => $type->labels->plural,
					'field'     => $field->field,
					'label'     => $field->plural,
					'entries'   => $this->content->query()->type($type->name)->whereTerm($field->termKey($profiles->name), $profile->slug)->count(),
					'archive'   => $this->urls->person($type, $field, $profile->slug),
					'page'      => $page === null ? null : ['id' => $page->id, 'handle' => $this->handles->of($page), 'title' => $page->title, 'status' => $page->status->value]
				];
			}
		}

		return $rows;
	}

	/**
	 * Returns a type and its people field when the field has archives,
	 * which is what a written page needs.
	 *
	 * @return ?array{ContentType, PeopleField}
	 */
	private function place(string $type, string $field): ?array
	{
		$contentType = $this->types->find($type);
		$people      = $contentType?->peopleField($field);

		return $contentType !== null && $people !== null && $contentType->folder !== '' && $this->urls->hasArchive($contentType, $people) ? [$contentType, $people] : null;
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
