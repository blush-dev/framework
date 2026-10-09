<?php

/**
 * Sync storage command.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console\Commands;

use Blush\Console\Attributes\Command;
use Blush\Console\ExitCode;
use Blush\Console\Output;
use Blush\Console\Verbosity;
use Blush\Storage\Record\RecordStores;
use Blush\Storage\Record\SchemaStore;
use Blush\Storage\Record\TableRegistry;

/**
 * Makes every registered table a database driver keeps, and brings each
 * up to date with its definition: its columns and indexes (D-644), then
 * gathers the statistics queries plan by. Stores
 * make a table when it's first used, so this is for deploys, before the
 * first request. Tables kept as files need nothing.
 */
#[Command('storage:sync', 'Make or update the tables a database driver keeps.')]
final readonly class SyncStorage
{
	public function __construct(
		private TableRegistry $tables,
		private RecordStores $stores
	) {}

	public function __invoke(Output $output): ExitCode
	{
		$synced = 0;
		$stores = [];

		foreach ($this->tables->all() as $table) {
			$store = $this->stores->store($table);

			if ($store instanceof SchemaStore) {
				$store->prepare($table);
				$output->line(sprintf('%s: %s', $table->area->value, $table->name), Verbosity::Verbose);
				$stores[spl_object_id($store)] = $store;
				$synced++;
			}
		}

		// Statistics, so queries pick the right index.
		foreach ($stores as $store) {
			$store->analyze();
		}

		if ($synced === 0) {
			$output->success('Nothing to do: the site keeps its data as files, which need no tables.');

			return ExitCode::Success;
		}

		$output->success(sprintf('Made or updated %d %s.', $synced, $synced === 1 ? 'table' : 'tables'));

		return ExitCode::Success;
	}
}
