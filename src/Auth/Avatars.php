<?php

/**
 * Avatars.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Auth;

use Blush\Content\Entry\Entry;
use Blush\Content\Type\Profiles;
use Blush\Media\MediaValues;
use Blush\Support\RequestMemo;
use Blush\View\Avatar;

/**
 * Builds avatars (D-809) for an account, wherever one is shown (the
 * admin, the activity log, mail, a template), and for a profile, which
 * may have no account (a guest author) as an account may have no profile
 * (a token-only agent). The picture is the profile's portrait (its
 * `avatar` media field), which wins over anything the account has, else
 * the name's initials. An account's Gravatar comes later, after the
 * portrait.
 *
 * Every avatar is kept in the `RequestMemo` (D-807), so an account and
 * its profile reached in two ways draw from one lookup.
 */
final readonly class Avatars
{
	public function __construct(
		private AccountProfiles $profiles,
		private MediaValues $media,
		private RequestMemo $memo
	) {}

	/**
	 * Returns an account's avatar: its profile's portrait, else its
	 * initials, named as the admin names it.
	 */
	public function account(Account $account, int $size = 48): Avatar
	{
		return $this->memo->remember("avatar|account|{$account->username}|{$size}", function () use ($account, $size): Avatar {
			$profile = $this->profiles->entry($account);

			return new Avatar($this->profiles->displayName($account), $size, $profile === null ? '' : $this->portrait($profile));
		});
	}

	/**
	 * Returns a profile's avatar: its portrait, else its initials. An
	 * entry that isn't a profile has initials only.
	 */
	public function profile(Entry $profile, int $size = 48): Avatar
	{
		$key = implode('|', ['avatar', 'profile', $profile->id ?? "path:{$profile->path}", $profile->version ?? '', $size]);

		return $this->memo->remember($key, fn (): Avatar => new Avatar($profile->title, $size, $this->portrait($profile)));
	}

	/**
	 * Returns the URL of a profile's portrait, or `''`.
	 */
	private function portrait(Entry $profile): string
	{
		return $profile->type instanceof Profiles ? $this->media->address($profile->field('avatar')) ?? '' : '';
	}
}
