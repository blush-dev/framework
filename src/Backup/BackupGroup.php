<?php

/**
 * Backup group.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Backup;

use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * The groups a backup's parts are shown in (D-883), as the sketch has
 * them, so ten parts read as three short lists; a plugin's parts are
 * under **Plugins**, and tables no part claims under **Other**.
 */
enum BackupGroup: string
{
	case Content = 'content';
	case Site    = 'site';
	case Users   = 'users';
	case Plugins = 'plugins';

	/**
	 * Returns the group's label.
	 */
	public function label(Messages $messages): Text
	{
		return match ($this) {
			self::Content => $messages->t('backup.group.content', 'Content'),
			self::Site    => $messages->t('backup.group.site', 'Site'),
			self::Users   => $messages->t('backup.group.users', 'Users'),
			self::Plugins => $messages->t('backup.group.plugins', 'Plugins')
		};
	}
}
