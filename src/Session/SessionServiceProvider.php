<?php

/**
 * Session service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Session;

use Blush\Core\ServiceProvider;
use Blush\Storage\StorageArea;

/**
 * Binds the session store (from the storage driver for sessions,
 * D-642, unless a site or extension binds another) and the `StartSession` middleware.
 */
final class SessionServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array STORAGE = [
		SessionStore::class => StorageArea::Sessions
	];

	/**
	 * @inheritDoc
	 */
	protected const array TRANSIENTS = [
		StartSession::class
	];
}
