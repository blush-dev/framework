<?php

/**
 * Admin dashboard controller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin\Http;

use DateTimeInterface;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Admin\EntryHandles;
use Blush\Auth\Account;
use Blush\Auth\AccountProfiles;
use Blush\Auth\Accounts;
use Blush\Auth\AuthException;
use Blush\Auth\Capability;
use Blush\Auth\ContentAction;
use Blush\Auth\Permissions;
use Blush\Auth\Tokens;
use Blush\Cache\ContentVersion;
use Blush\Content\Entries;
use Blush\Content\Entry\Entry;
use Blush\Storage\Record\Order;
use Blush\Content\Query\Query;
use Blush\Content\Relation\RelationKind;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Status;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\BuiltInType;
use Blush\Content\Type\TypeKind;
use Blush\Core\AppConfig;
use Blush\Http\Response;
use Blush\Http\Status as HttpStatus;
use Blush\Media\Index\MediaLibrary;
use Blush\Media\Index\MediaQuery;
use Blush\Translation\Messages;

/**
 * Answers `GET {path}/api/dashboard` (D-538): the site, and the entries
 * that need the account first, then the rest.
 *
 * - `published`: how many entries the site has published, which decides
 *   whether the setup path takes the dashboard's place.
 * - `resume`: the entry the account last saved (D-539), unless it's in
 *   the trash or the account may no longer edit it, or `null`.
 * - `yours` and `everyone`: entries of pages and collections the account
 *   may edit, grouped by state: `draft` (last changed first),
 *   `scheduled` (soonest first), and `published` (newest first), at most
 *   `PER_GROUP` in each. `yours` holds those crediting the account's
 *   profile, and is `null` when the account has none. Neither repeats
 *   the `resume` entry. Landing pages are left out: they're their type's
 *   archive.
 * - `setup`: what the setup path needs to know: the pages type, the
 *   site's newest page, whether it has a type of its own, whether the
 *   library has files (`media`, with a media capability), and whether
 *   another account exists (`others`, with `accounts.view`; D-800).
 *   Either is `false` without its capability.
 * - `tokens`: the account's API tokens that expire within
 *   `TOKEN_WARNING` (D-748), soonest first, each its `id`, `name`, and
 *   `expires`.
 *
 * Actions moved to the Tools screen (`GET actions`, D-540).
 */
final readonly class DashboardController
{
	/**
	 * The most entries in each group.
	 */
	public const int PER_GROUP = 5;

	/**
	 * How long, in seconds, before a token expires it's shown.
	 */
	public const int TOKEN_WARNING = 7 * 86400;

	public function __construct(
		private AppConfig $site,
		private Entries $content,
		private ContentTypes $types,
		private ContentUrls $urls,
		private ContentVersion $version,
		private EntryHandles $handles,
		private Permissions $permissions,
		private AccountProfiles $profiles,
		private Tokens $tokens,
		private Accounts $accounts,
		private MediaLibrary $media,
		private ClockInterface $clock,
		private Messages $messages
	) {}

	public function __invoke(ServerRequestInterface $request): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account) {
			return Response::json(['error' => $this->messages->t('admin.common.sign_in_first', 'Sign in first.')], HttpStatus::Unauthorized, ['Cache-Control' => 'no-store']);
		}

		$resume   = $this->resume($account);
		$except   = $resume?->id;
		$profiles = $this->types->profiles()?->name;
		$own      = $this->profiles->slug($account);
		$base     = $this->permissions->restrict($account, ContentAction::Edit, $this->content->query()->any()->withLanding(false)->type(...$this->listed()));

		return Response::json([
			'site'      => [
				'name'        => $this->site->name,
				'url'         => $this->site->url,
				'environment' => $this->site->environment->value,
				'version'     => $this->version->current()
			],
			'published' => $this->content->query()->status(Status::Published)->count(),
			'resume'    => $resume === null ? null : $this->describe($resume, $account),
			'yours'     => $profiles === null || $own === null ? null : $this->groups($base->whereTerm($profiles, $own), $account, $except),
			'everyone'  => $this->groups($base, $account, $except),
			'setup'     => $this->setup($account),
			'tokens'    => $this->expiring($account)
		], headers: ['Cache-Control' => 'no-store']);
	}

	/**
	 * Returns the account's tokens that expire soon, soonest first.
	 *
	 * @return list<array{id: string, name: string, expires: int}>
	 */
	private function expiring(Account $account): array
	{
		$now  = $this->clock->now()->getTimestamp();
		$soon = [];

		foreach ($this->tokens->forAccount($account) as $token) {
			if ($token->expires !== null && $token->expires > $now && $token->expires <= $now + self::TOKEN_WARNING) {
				$soon[] = ['id' => $token->id, 'name' => $token->name, 'expires' => $token->expires];
			}
		}

		usort($soon, static fn (array $a, array $b): int => $a['expires'] <=> $b['expires']);

		return $soon;
	}

	/**
	 * Returns the entry the account last saved, whatever its status but
	 * the trash, while it's theirs to edit.
	 */
	private function resume(Account $account): ?Entry
	{
		$id    = $account->preferences->lastEdited;
		$entry = $id === null ? null : $this->content->find($id);

		return $entry !== null
			&& $entry->status !== Status::Trash
			&& $this->permissions->can($account, ContentAction::Edit, $entry)
			? $entry
			: null;
	}

	/**
	 * Returns a query's entries by state, without the one being resumed.
	 *
	 * @return array{draft: list<array<string, mixed>>, scheduled: list<array<string, mixed>>, published: list<array<string, mixed>>}
	 */
	private function groups(Query $query, Account $account, ?string $except): array
	{
		$take  = function (Query $query) use ($account, $except): array {
			$entries = array_filter($query->limit(self::PER_GROUP + 1)->get()->all(), static fn (Entry $entry): bool => $except === null || $entry->id !== $except);

			return array_map(fn (Entry $entry): array => $this->describe($entry, $account), array_slice(array_values($entries), 0, self::PER_GROUP));
		};

		return [
			'draft'     => $take($query->status(Status::Draft)->orderBy('updated', Order::Desc)),
			'scheduled' => $take($query->status(Status::Scheduled)->orderBy('published', Order::Asc)),
			'published' => $take($query->status(Status::Published)->orderBy('published', Order::Desc))
		];
	}

	/**
	 * Returns what the setup path needs: the pages type's name (`null`
	 * when it's off), the newest of its entries the account may edit,
	 * whatever its status, whether the site defines pages or a
	 * collection of its own, and whether the library has files and
	 * another account exists, as far as the account may see.
	 *
	 * @return array{type: ?string, page: ?array<string, mixed>, ownTypes: bool, media: bool, others: bool}
	 */
	private function setup(Account $account): array
	{
		$pages = $this->types->find(BuiltInType::Page->value);
		$entry = $pages === null ? null : $this->permissions->restrict($account, ContentAction::Edit, $this->content->query()->any()->type($pages->name))->orderBy('updated', Order::Desc)->first();
		$own   = array_filter($this->listed(), static fn (string $name): bool => BuiltInType::tryFrom($name) === null);

		return [
			'type'     => $pages?->name,
			'page'     => $entry === null ? null : $this->describe($entry, $account),
			'ownTypes' => $own !== [],
			'media'    => $this->permissions->usesMedia($account) && $this->media->query(new MediaQuery(per: 1))->total > 0,
			'others'   => $this->permissions->can($account, Capability::AccountsView) && $this->others()
		];
	}

	/**
	 * Returns whether the site has more than one account. A damaged
	 * record answers no.
	 */
	private function others(): bool
	{
		try {
			return count($this->accounts->all()) > 1;
		} catch (AuthException) {
			return false;
		}
	}

	/**
	 * Returns the names of the types the dashboard lists: pages and
	 * collections. Terms and profiles are added by using them, not
	 * written.
	 *
	 * @return list<string>
	 */
	private function listed(): array
	{
		$names = [];

		foreach ($this->types->all() as $type) {
			if ($type->kind() === TypeKind::Tree || $type->kind() === TypeKind::Collection) {
				$names[] = $type->name;
			}
		}

		return $names;
	}

	/**
	 * Returns what the dashboard shows of an entry. `authors` are the
	 * names of the profiles its credit relations link (D-803), and `yours`
	 * says whether one of them is the account's profile.
	 *
	 * @return array<string, mixed>
	 */
	private function describe(Entry $entry, Account $account): array
	{
		$credited = [];

		foreach ($entry->relations(RelationKind::Credit) as $relation) {
			foreach ($entry->related($relation->name) as $profile) {
				$credited[$profile->id ?? $profile->path] = $profile;
			}
		}

		return [
			'id'        => $entry->id,
			'path'      => $entry->path,
			'handle'    => $this->handles->of($entry),
			'title'     => $entry->title,
			'type'      => $entry->type->name,
			'status'    => $entry->status->value,
			'url'       => $this->urls->entry($entry),
			'published' => $entry->published?->format(DateTimeInterface::ATOM),
			'updated'   => $entry->updated->format(DateTimeInterface::ATOM),
			'authors'   => array_values(array_map(static fn (Entry $profile): string => trim($profile->title) === '' ? $profile->slug : trim($profile->title), $credited)),
			'yours'     => $account->profile !== null && isset($credited[$account->profile])
		];
	}
}
