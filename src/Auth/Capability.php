<?php

/**
 * Built-in capabilities.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Auth;

/**
 * The framework's site capabilities (D-217), seeded into `Capabilities`,
 * where extensions add their own. Managing accounts and roles is seven
 * of them (D-362); each `accounts.*` beyond `view` also needs `view`. What a role may do to entries is per
 * content type (`ContentAction`, D-359).
 *
 * Media (D-407): editing a file's details and deleting it are by whose
 * file it is (its uploader's, or `.others` for anyone else's and for
 * files with no uploader), and need the same action on their own to use
 * `.others`, as content's do. Uploading is by kind
 * (`media.{kind}.upload`, `MediaKind::uploadCapability()`), with
 * `media.*.upload` for every kind.
 *
 * HTML (D-495): adding raw HTML to a body in the admin, from the allowed
 * list (`html.allowed`) or anything but what's always refused
 * (`html.unfiltered`; `Markdown\Html\HtmlRules`). Without either, a save
 * that adds HTML is refused. Either is enough on its own.
 */
enum Capability: string
{
	case MediaEdit         = 'media.edit';
	case MediaEditOthers   = 'media.edit.others';
	case MediaDelete       = 'media.delete';
	case MediaDeleteOthers = 'media.delete.others';
	case HtmlAllowed       = 'html.allowed';
	case HtmlUnfiltered    = 'html.unfiltered';
	case MenusEdit       = 'menus.edit';
	case RegionsEdit     = 'regions.edit';
	case SitePublish     = 'site.publish';
	case CacheClear      = 'cache.clear';
	case SiteSettings    = 'site.settings';
	case AccountsView    = 'accounts.view';
	case AccountsCreate  = 'accounts.create';
	case AccountsEdit    = 'accounts.edit';
	case AccountsRoles   = 'accounts.roles';
	case AccountsSuspend = 'accounts.suspend';
	case AccountsDelete  = 'accounts.delete';
	case RolesManage     = 'roles.manage';

	/**
	 * Capabilities that are gone (D-407): a saved role that names one has
	 * it dropped when it's read, and loses it on its next save. Nothing
	 * takes its place.
	 *
	 * @var list<string>
	 */
	public const array RETIRED = ['media.upload'];

	/**
	 * The `.others` form of a media capability, or `null` for one that has
	 * none.
	 */
	public function others(): ?self
	{
		return match ($this) {
			self::MediaEdit   => self::MediaEditOthers,
			self::MediaDelete => self::MediaDeleteOthers,
			default           => null
		};
	}

	/**
	 * The capabilities for managing accounts and roles (D-362). Someone
	 * who isn't suspended must always hold all of them.
	 *
	 * @return list<self>
	 */
	public static function users(): array
	{
		return [self::AccountsView, self::AccountsCreate, self::AccountsEdit, self::AccountsRoles, self::AccountsSuspend, self::AccountsDelete, self::RolesManage];
	}

	/**
	 * Returns the group the admin shows the capability in.
	 */
	public function group(): string
	{
		return match ($this) {
			self::MediaEdit, self::MediaEditOthers,
			self::MediaDelete, self::MediaDeleteOthers              => 'Media',
			self::HtmlAllowed, self::HtmlUnfiltered                 => 'HTML',
			self::MenusEdit, self::RegionsEdit                      => 'Structure',
			self::SitePublish, self::CacheClear, self::SiteSettings => 'Site',
			default                                                 => 'Users'
		};
	}

	/**
	 * Returns the capability's label.
	 */
	public function label(): string
	{
		return match ($this) {
			self::MediaEdit         => 'Edit their own files',
			self::MediaEditOthers   => 'Edit anyone\'s files',
			self::MediaDelete       => 'Delete their own files',
			self::MediaDeleteOthers => 'Delete anyone\'s files',
			self::HtmlAllowed       => 'Add HTML from the allowed list',
			self::HtmlUnfiltered    => 'Add any HTML but what\'s always refused',
			self::MenusEdit       => 'Edit menus',
			self::RegionsEdit     => 'Edit regions',
			self::SitePublish     => 'Publish the site',
			self::CacheClear      => 'Clear caches',
			self::SiteSettings    => 'Change site settings',
			self::AccountsView    => 'See accounts and roles',
			self::AccountsCreate  => 'Create accounts',
			self::AccountsEdit    => 'Edit accounts',
			self::AccountsRoles   => 'Give and take roles',
			self::AccountsSuspend => 'Suspend and reinstate accounts',
			self::AccountsDelete  => 'Remove accounts',
			self::RolesManage     => 'Manage roles'
		};
	}
}
