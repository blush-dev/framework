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

namespace Blush\Admin;

use DateTimeInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Blush\Auth\Account;
use Blush\Auth\ContentAction;
use Blush\Auth\Permissions;
use Blush\Cache\ContentVersion;
use Blush\Content\ContentRepository;
use Blush\Content\Entry\Entry;
use Blush\Storage\Record\Order;
use Blush\Content\Query\Query;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Status;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Type\BuiltInType;
use Blush\Content\Type\TypeKind;
use Blush\Core\AppConfig;
use Blush\Http\Response;
use Blush\Http\Status as HttpStatus;

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
 *   site's newest page, and whether it has a type of its own.
 *
 * Actions moved to the Tools screen (`GET actions`, D-540).
 */
final readonly class DashboardController
{
	/**
	 * The most entries in each group.
	 */
	public const int PER_GROUP = 5;

	public function __construct(
		private AppConfig $site,
		private ContentRepository $content,
		private ContentTypes $types,
		private ContentUrls $urls,
		private ContentVersion $version,
		private EntryHandles $handles,
		private Permissions $permissions
	) {}

	public function __invoke(ServerRequestInterface $request): ResponseInterface
	{
		$account = $request->getAttribute(Account::class);

		if (! $account instanceof Account) {
			return Response::json(['error' => 'Sign in first.'], HttpStatus::Unauthorized, ['Cache-Control' => 'no-store']);
		}

		$resume   = $this->resume($account);
		$except   = $resume?->path;
		$profiles = $this->types->profiles()?->name;
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
			'yours'     => $profiles === null || $account->author === null ? null : $this->groups($base->whereTerm($profiles, $account->author), $account, $except),
			'everyone'  => $this->groups($base, $account, $except),
			'setup'     => $this->setup($account)
		], headers: ['Cache-Control' => 'no-store']);
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
			$entries = array_filter($query->limit(self::PER_GROUP + 1)->get()->all(), static fn (Entry $entry): bool => $entry->path !== $except);

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
	 * whatever its status, and whether the site defines pages or a
	 * collection of its own.
	 *
	 * @return array{type: ?string, page: ?array<string, mixed>, ownTypes: bool}
	 */
	private function setup(Account $account): array
	{
		$pages = $this->types->find(BuiltInType::Page->value);
		$entry = $pages === null ? null : $this->permissions->restrict($account, ContentAction::Edit, $this->content->query()->any()->type($pages->name))->orderBy('updated', Order::Desc)->first();
		$own   = array_filter($this->listed(), static fn (string $name): bool => BuiltInType::tryFrom($name) === null);

		return [
			'type'     => $pages?->name,
			'page'     => $entry === null ? null : $this->describe($entry, $account),
			'ownTypes' => $own !== []
		];
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
	 * names of the people it credits, and `yours` says whether one of them
	 * is the account's profile.
	 *
	 * @return array<string, mixed>
	 */
	private function describe(Entry $entry, Account $account): array
	{
		$profiles = $this->types->profiles()?->name;
		$credited = $profiles === null ? [] : $entry->terms($profiles);

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
			'authors'   => array_map(fn (string $slug): string => $this->name($profiles, $slug), $credited),
			'yours'     => $account->author !== null && in_array($account->author, $credited, true)
		];
	}

	/**
	 * Returns the name of a person an entry credits: their profile's
	 * title, or the slug when it has none.
	 */
	private function name(?string $profiles, string $slug): string
	{
		$title = $profiles === null ? '' : trim($this->content->term($profiles, $slug)->title ?? '');

		return $title === '' ? $slug : $title;
	}
}
