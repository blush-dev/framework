<?php

/**
 * The API's entries.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Api;

use Uri\Rfc3986\Uri;
use Blush\Auth\Account;
use Blush\Auth\AuthException;
use Blush\Auth\ContentAction;
use Blush\Auth\Permissions;
use Blush\Auth\Token;
use Blush\Cache\CacheNamespace;
use Blush\Cache\ContentCache;
use Blush\Content\Entries;
use Blush\Content\Entry\Entry;
use Blush\Content\Query\Query;
use Blush\Content\Routing\ContentUrls;
use Blush\Content\Status;
use Blush\Content\Type\ContentType;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Visibility;
use Blush\Core\AppConfig;

/**
 * The entries the API answers with: what the public site shows (D-479).
 *
 * - **One by id or address:** published, of a public type, and public
 *   or unlisted (an unlisted entry has a page, so it can be asked for).
 * - **Lists:** published, public, of public types, without listings'
 *   landing pages, as collections and feeds list them.
 *
 * A hidden entry, a draft, a scheduled entry, and anything of a type
 * that isn't public are never in an answer, not even as a stub
 * (D-738), but one to a request with an API token that asks for them
 * by status (D-745): those of the public types, whatever their
 * visibility, that the token may edit (`content.{type}.edit` for its
 * account's own, `.edit.others` for anyone else's, as the admin
 * decides). Links from them to entries the site doesn't show are still
 * left out.
 */
final readonly class ApiEntries
{
	public function __construct(
		private Entries $content,
		private ContentTypes $types,
		private ContentUrls $urls,
		private ContentCache $cache,
		private AppConfig $app,
		private Permissions $permissions
	) {}

	/**
	 * Returns whether the API may show an entry.
	 */
	public function shows(Entry $entry): bool
	{
		return $entry->isPublished() && $entry->type->public && $entry->isRoutable();
	}

	/**
	 * Returns an entry by id when the API may show it, or `null`.
	 */
	public function find(string $id): ?Entry
	{
		$entry = $this->content->find($id);

		return $entry !== null && $this->shows($entry) ? $entry : null;
	}

	/**
	 * Returns an entry by id when a token asking for some statuses may
	 * see it: of a public type, with one of them, and its to edit.
	 *
	 * @param  list<Status> $statuses
	 * @throws AuthException For a token whose capabilities aren't names.
	 */
	public function findEditable(string $id, Token $token, Account $account, array $statuses): ?Entry
	{
		$entry = $this->content->find($id);

		return $entry !== null
			&& in_array($entry->status, $statuses, true)
			&& isset($this->types()[$entry->type->name])
			&& $this->permissions->limitedTo($token->capabilities)->can($account, ContentAction::Edit, $entry)
			? $entry
			: null;
	}

	/**
	 * Returns a query for the entries of some statuses a token may edit,
	 * of the public types, whatever their visibility, without listings'
	 * landing pages.
	 *
	 * @param  list<Status> $statuses
	 * @throws AuthException For a token whose capabilities aren't names.
	 */
	public function editable(Token $token, Account $account, array $statuses): Query
	{
		$query = $this->content->query()
			->status(...$statuses)
			->visibility(...Visibility::cases())
			->withLanding(false)
			->type(...array_keys($this->types()));

		return $this->permissions->limitedTo($token->capabilities)->restrict($account, ContentAction::Edit, $query);
	}

	/**
	 * Returns the public types, by name.
	 *
	 * @return array<string, ContentType>
	 */
	public function types(): array
	{
		$types = [];

		foreach ($this->types->all() as $type) {
			if ($type->public) {
				$types[$type->name] = $type;
			}
		}

		return $types;
	}

	/**
	 * Returns a query for what lists show, of the public types.
	 */
	public function query(): Query
	{
		return $this->content->query()
			->status(Status::Published)
			->visibility(Visibility::Public)
			->withLanding(false)
			->type(...array_keys($this->types()));
	}

	/**
	 * Returns an entry's address as a full URL, or `null` when it has
	 * none.
	 */
	public function url(Entry $entry): ?string
	{
		$url = $this->urls->entry($entry);

		return $url === null ? null : $this->urls->absolute($url);
	}

	/**
	 * Returns whether entries of a type have addresses, whether they're
	 * routed or served as pages.
	 */
	public function hasUrls(ContentType $type): bool
	{
		return $type->hasUrls() || $type->servedAsPages();
	}

	/**
	 * Returns the entry at a site address (D-740), a path or a full URL
	 * on the site's origin, or `null`. Its query and fragment don't
	 * count, nor does a trailing slash.
	 */
	public function at(string $address): ?Entry
	{
		$path = self::path($address, $this->app->origin());

		if ($path === null) {
			return null;
		}

		$id = $this->addresses()[$path] ?? null;

		return $id === null ? null : $this->find($id);
	}

	/**
	 * Returns an address's path, or `null` for one on another site.
	 */
	public static function path(string $address, string $origin): ?string
	{
		$address = trim($address);

		if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $address) === 1) {
			if (! str_starts_with(strtolower($address), strtolower($origin) . '/') && strtolower($address) !== strtolower($origin)) {
				return null;
			}

			$address = substr($address, strlen($origin));
		}

		$uri = Uri::parse('/' . ltrim($address, '/'));

		return $uri === null ? null : (rtrim(rawurldecode($uri->getPath()), '/') ?: '/');
	}

	/**
	 * Returns the id of the entry at each address, by path, kept for the
	 * content version.
	 *
	 * @return array<string, string>
	 */
	private function addresses(): array
	{
		/** @var array<string, string> */
		return $this->cache->remember(CacheNamespace::Fragments, 'api addresses', function (): array {
			$entries = $this->content->query()
				->any()
				->status(Status::Published)
				->visibility(Visibility::Public, Visibility::Unlisted)
				->type(...array_keys($this->types()))
				->get();

			$home      = $this->types->home;
			$addresses = [];

			foreach ($entries as $entry) {
				$url = $entry->id === null ? null : $this->urls->entry($entry);

				// With a home type, its collection is `/`, not the root page.
				if ($url === null || ($url === '/' && $home !== null && $entry->type->name !== $home)) {
					continue;
				}

				$addresses[rtrim($url, '/') ?: '/'] ??= (string) $entry->id;
			}

			return $addresses;
		});
	}
}
