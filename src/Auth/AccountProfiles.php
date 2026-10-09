<?php

/**
 * Account profiles.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Auth;

use Psr\Clock\ClockInterface;
use Blush\Content\Entries;
use Blush\Content\Entry\Entry;
use Blush\Content\Type\ContentTypes;
use Blush\Content\Writer\EntryChanges;
use Blush\Content\Writer\WriteException;

/**
 * An account's profile (D-351): the entry of the site's profiles type an
 * account is linked to, by the entry's id (D-668), so renaming the
 * profile never breaks the link. People and the admin name a profile by
 * its slug, and this turns one into the other.
 *
 * Linking to a slug with no profile yet makes it, as a draft titled with
 * the name given (else the slug): an account is never linked to nothing.
 * Kept apart from `Accounts`, which reads accounts without the content
 * layer, as signing in does.
 */
final readonly class AccountProfiles
{
	public function __construct(
		private Accounts $accounts,
		private Entries $content,
		private ContentTypes $types,
		private ClockInterface $clock
	) {}

	/**
	 * Returns an account's profile entry, or `null` when it has none, or
	 * its profile is gone.
	 */
	public function entry(Account $account): ?Entry
	{
		$type  = $this->types->profiles()?->name;
		$entry = $account->profile === null || $type === null ? null : $this->content->find($account->profile);

		return $entry !== null && $entry->type->name === $type ? $entry : null;
	}

	/**
	 * Returns the slug of an account's profile, or `null`.
	 */
	public function slug(Account $account): ?string
	{
		return $this->entry($account)?->key;
	}

	/**
	 * Returns a profile by its slug, or `null`.
	 */
	public function find(string $slug): ?Entry
	{
		$type = $this->types->profiles()?->name;

		return $type === null || $slug === '' ? null : $this->content->named($type, $slug);
	}

	/**
	 * Returns the account linked to a profile, by its slug, other than
	 * one, or `null`.
	 *
	 * @throws AuthException When an account's record is damaged.
	 */
	public function accountFor(string $slug, ?string $except = null): ?Account
	{
		$id = $this->find($slug)?->id;

		return $id === null ? null : $this->accounts->linkedTo($id, $except);
	}

	/**
	 * Returns what the admin calls an account: its own name (D-322,
	 * D-370), else its profile's title, else its username.
	 */
	public function displayName(Account $account): string
	{
		if ($account->name !== null) {
			return $account->name;
		}

		$title = $this->entry($account)->title ?? '';

		return $title !== '' ? $title : $account->username;
	}

	/**
	 * Whether a profile can be linked to an account (D-605): `linkable:
	 * false` in its front matter locks it, for a byline no one should
	 * sign in as (an organization, someone who has died, an imported
	 * contributor). A profile not made yet can be.
	 */
	public function isLinkable(string $slug): bool
	{
		return $this->find($slug)?->field('linkable') !== false;
	}

	/**
	 * Returns the id of the profile at a slug, for linking an account to
	 * it, making the profile when there's none: a draft titled `$title`,
	 * else the slug. `null` links nothing.
	 *
	 * @throws AuthException When the site has no profiles type, the
	 *                       profile is locked or another account's, or it
	 *                       can't be made.
	 */
	public function prepare(?string $slug, ?string $title = null, ?string $username = null): ?string
	{
		if ($slug === null || $slug === '') {
			return null;
		}

		$type    = $this->types->profiles() ?? throw new AuthException('The site has no profiles type.');
		$profile = $this->content->named($type->name, $slug);

		if ($profile === null) {
			try {
				$profile = $this->content->create($type, $slug, new EntryChanges(set: ['title' => $title === null || trim($title) === '' ? $slug : trim($title), 'status' => 'draft'], body: "\n"), null, $this->clock->now());
			} catch (WriteException $e) {
				throw new AuthException($e->getMessage(), previous: $e);
			}
		}

		$other = $profile->id === null ? null : $this->accounts->linkedTo($profile->id, $username);

		if ($other !== null) {
			throw new AuthException(sprintf('The "%s" profile is %s\'s already; a profile belongs to one account.', $slug, $this->displayName($other)));
		}

		if ($profile->field('linkable') === false && ($username === null || $this->accounts->find($username)?->profile !== $profile->id)) {
			throw new AuthException(sprintf('The "%s" profile is locked, so no account can be linked to it. Unlock it on its screen first.', $slug));
		}

		return $profile->id ?? throw new AuthException(sprintf('The "%s" profile has no id yet; run `content:ids` first.', $slug));
	}

	/**
	 * Links an account to the profile at a slug, making it when there's
	 * none (see `prepare()`), or unlinks it (`null`).
	 *
	 * @throws AuthException As `prepare()` does.
	 */
	public function link(Account $account, ?string $slug, ?string $title = null): Account
	{
		return $this->accounts->setProfile($account, $this->prepare($slug, $title, $account->username));
	}

	/**
	 * Whether a profile would be made by linking to a slug: there's no
	 * profile there yet.
	 */
	public function wouldCreate(?string $slug): bool
	{
		return $slug !== null && $slug !== '' && $this->find($slug) === null;
	}
}
