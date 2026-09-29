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
 * The framework's capabilities (D-217), seeded into `Capabilities`, where
 * extensions add their own. A `.others` capability extends its base to
 * entries the account doesn't own (see `Permissions`).
 */
enum Capability: string
{
	case ContentCreate        = 'content.create';
	case ContentEdit          = 'content.edit';
	case ContentEditOthers    = 'content.edit.others';
	case ContentPublish       = 'content.publish';
	case ContentPublishOthers = 'content.publish.others';
	case ContentDelete        = 'content.delete';
	case ContentDeleteOthers  = 'content.delete.others';
	case MediaUpload          = 'media.upload';
	case MediaDelete          = 'media.delete';
	case MenusEdit            = 'menus.edit';
	case RegionsEdit          = 'regions.edit';
	case SitePublish          = 'site.publish';
	case CacheClear           = 'cache.clear';
	case SiteSettings         = 'site.settings';
	case AccountsManage       = 'accounts.manage';

	/**
	 * Returns the capability's label.
	 */
	public function label(): string
	{
		return match ($this) {
			self::ContentCreate        => 'Create entries',
			self::ContentEdit          => 'Edit their own entries',
			self::ContentEditOthers    => 'Edit others\' entries',
			self::ContentPublish       => 'Publish their own entries',
			self::ContentPublishOthers => 'Publish others\' entries',
			self::ContentDelete        => 'Delete their own entries',
			self::ContentDeleteOthers  => 'Delete others\' entries',
			self::MediaUpload          => 'Upload media',
			self::MediaDelete          => 'Delete media',
			self::MenusEdit            => 'Edit menus',
			self::RegionsEdit          => 'Edit regions',
			self::SitePublish          => 'Publish the site',
			self::CacheClear           => 'Clear caches',
			self::SiteSettings         => 'Change site settings',
			self::AccountsManage       => 'Manage accounts'
		};
	}
}
