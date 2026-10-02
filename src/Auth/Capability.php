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
 */
enum Capability: string
{
	case MediaUpload     = 'media.upload';
	case MediaDelete     = 'media.delete';
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
			self::MediaUpload, self::MediaDelete                    => 'Media',
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
			self::MediaUpload     => 'Upload media',
			self::MediaDelete     => 'Delete media',
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
