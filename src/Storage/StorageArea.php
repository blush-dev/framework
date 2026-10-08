<?php

/**
 * Storage areas.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage;

/**
 * The kinds of site data a storage driver keeps (D-486): what a site
 * would keep in a database instead of files. Media files aren't one;
 * they stay files whatever the driver (their metadata is `Data`).
 */
enum StorageArea: string
{
	/**
	 * Entries (`user/content`).
	 */
	case Content = 'content';

	/**
	 * Site data (`user/data`): settings, types, field sets, menus,
	 * regions, redirects, and media metadata.
	 */
	case Data = 'data';

	/**
	 * Accounts and roles (`storage/accounts`, `storage/roles.json`).
	 */
	case Accounts = 'accounts';

	/**
	 * Login sessions (`storage/sessions`).
	 */
	case Sessions = 'sessions';

	/**
	 * Background jobs and the scheduler's state (`storage/jobs`, D-621).
	 */
	case Jobs = 'jobs';
}
