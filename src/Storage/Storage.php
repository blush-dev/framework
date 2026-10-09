<?php

/**
 * Storage.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Storage;

/**
 * A storage driver (D-486, D-642): the classes that keep a site's data
 * one way, files or a database, by the contract each implements. Each
 * subsystem names its contracts and their area (`ServiceProvider`'s
 * `STORAGE`), and the container builds the class the area's driver
 * gives, so each can ask for the services it needs, a database
 * connection or `Entries` alike. A driver may offer commands of its own
 * for an area, tools only its way of keeping data has (D-654).
 *
 * A driver needn't cover every area: one for sessions and jobs only
 * leaves the rest to others (D-640). A site naming it for an area it
 * doesn't cover fails, saying which contract is missing.
 */
interface Storage
{
	/**
	 * Returns the class that implements each contract.
	 *
	 * @return array<class-string, class-string>
	 */
	public function bindings(): array;

	/**
	 * Returns the commands the driver offers for an area it keeps, tools
	 * only its way of keeping data has (D-654), such as renaming files to
	 * a pattern. They're registered while the area uses the driver.
	 *
	 * @return list<class-string>
	 */
	public function commands(StorageArea $area): array;
}
