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
		$synced = [];
		$stores = [];
		$tables = $this->tables->all();
		$steps  = $output->steps();

		foreach (array_values($tables) as $done => $table) {
			$steps('Syncing tables', $done, count($tables));
			$store = $this->stores->store($table);

			if ($store instanceof SchemaStore) {
				$store->prepare($table);
				$stores[spl_object_id($store)] = $store;
				$synced[] = sprintf('%s: %s', $table->area->value, $table->name);
			}
		}

		$steps('Syncing tables', count($tables), count($tables));

		// Statistics, so queries pick the right index.
		foreach (array_values($stores) as $done => $store) {
			$steps('Gathering statistics', $done, count($stores));
			$store->analyze();
			$steps('Gathering statistics', $done + 1, count($stores));
		}

		$steps->finish();

		foreach ($synced as $line) {
			$output->line($line, Verbosity::Verbose);
		}

		$synced = count($synced);

		if ($synced === 0) {
			$output->success('Nothing to do: the site keeps its data as files, which need no tables.');

			return ExitCode::Success;
		}

		$output->success(sprintf('Made or updated %d %s.', $synced, $synced === 1 ? 'table' : 'tables'));

		return ExitCode::Success;
	}
}
