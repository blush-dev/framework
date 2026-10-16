<?php

/**
 * Backup made.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Backup\Events;

use Blush\Backup\Backup;

/**
 * Dispatched once a backup is made and kept (D-899), however it was made:
 * by hand, on the schedule, on the command line, or before a restore
 * (its `kind` says), with the `.zip` it's kept in. Not for one uploaded.
 * A plugin listens for it to copy backups off the server (S3, SFTP, and
 * the like), so they outlive it. The archive holds password hashes, so
 * a copy should go somewhere as private.
 *
 * It's dispatched in the job (or command) that made the backup; a slow
 * copy belongs in a job of its own, queued from the listener.
 */
final readonly class BackupMade
{
	public function __construct(
		public Backup $backup,
		public string $file
	) {}
}
