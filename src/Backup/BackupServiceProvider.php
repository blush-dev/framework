<?php

/**
 * Backup service provider.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Backup;

use Override;
use Blush\Container\ServiceResolver;
use Blush\Core\ServiceProvider;
use Blush\Job\JobType;
use Blush\Job\Schedule;

/**
 * Binds the site's backups (D-881): the parts a backup holds, the folder
 * they're kept in, what makes and restores them, and who hears about the
 * schedule's. Each storage driver gives its own
 * `TableBackup` (D-882).
 */
final class BackupServiceProvider extends ServiceProvider
{
	/**
	 * @inheritDoc
	 */
	protected const array SINGLETONS = [
		BackupParts::class,
		Backups::class,
		BackupMaker::class,
		BackupRestorer::class,
		BackupNotices::class
	];

	/**
	 * Puts the site's backup on the schedule when it has one (D-887).
	 */
	#[Override]
	public function register(): void
	{
		$this->container->resolving(Schedule::class, static function (object $schedule, ServiceResolver $resolver): void {
			$when = $resolver->make(BackupConfig::class)->schedule->when();

			if ($schedule instanceof Schedule && $when !== null) {
				$schedule->add(JobType::ScheduledBackup->value, $when);
			}
		});
	}
}
