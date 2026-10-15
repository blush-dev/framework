<?php

/**
 * Workflow tables.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Workflow;

use Psr\Clock\ClockInterface;
use Blush\Storage\Record\KeyedTable;
use Blush\Storage\Record\RecordStores;
use Blush\Storage\Record\Table;
use Blush\Storage\StorageArea;

/**
 * The tables of the statuses and workflows a site defines (D-735,
 * D-853): `statuses` and `workflows`, keyed by `name`, in the data area.
 * On files, each is a folder of JSON files named for their records
 * (`user/data/statuses/in_review.json`), the name never written inside.
 *
 * A status record has `label`, `plural`, `tone`, `live`, `active`, and
 * `subject`; one named for a core or extension status sets only its
 * `label`, `plural`, and `tone`. A workflow record has `label`,
 * `statuses`, `initial`, `transitions` (each `name`, `label`, `from`,
 * `to`, and `capability`), and `subject`, and replaces a workflow by its
 * name, `standard` too.
 */
final readonly class WorkflowTables
{
	/**
	 * The statuses' table's name, and its folder under `user/data`.
	 */
	public const string STATUSES = 'statuses';

	/**
	 * The workflows' table's name, and its folder under `user/data`.
	 */
	public const string WORKFLOWS = 'workflows';

	public function __construct(
		private RecordStores $stores,
		private ClockInterface $clock
	) {}

	/**
	 * The statuses' table.
	 */
	public static function statusesTable(): Table
	{
		return new Table(self::STATUSES, StorageArea::Data, key: 'name', fields: ['name']);
	}

	/**
	 * The workflows' table.
	 */
	public static function workflowsTable(): Table
	{
		return new Table(self::WORKFLOWS, StorageArea::Data, key: 'name', fields: ['name']);
	}

	/**
	 * The site's statuses, by name.
	 */
	public function statuses(): KeyedTable
	{
		return new KeyedTable($this->stores, self::statusesTable(), $this->clock);
	}

	/**
	 * The site's workflows, by name.
	 */
	public function workflows(): KeyedTable
	{
		return new KeyedTable($this->stores, self::workflowsTable(), $this->clock);
	}
}
