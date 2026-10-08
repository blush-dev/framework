<?php

/**
 * Ignored Site Health problems, in a file.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

use Closure;
use Override;
use Blush\Data\DataException;
use Blush\Data\DataStore;

/**
 * Keeps the ignored Site Health problems in the data store's
 * `health/ignored` record (D-613, D-642; `user/data/health/ignored.json`
 * for files): site data, kept with the site, not derived from it as the
 * last report is. A record that can't be read ignores nothing.
 */
final readonly class StoredIgnoredProblems implements IgnoredProblems
{
	/**
	 * The record's name in the data store.
	 */
	public const string RECORD = 'health/ignored';

	public function __construct(
		private DataStore $data
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function all(): array
	{
		try {
			$data = $this->data->load(self::RECORD) ?? [];
		} catch (DataException) {
			return [];
		}

		$ignored = [];

		foreach ($data as $key => $record) {
			if (is_string($key) && is_array($record) && is_string($record['by'] ?? null) && is_string($record['at'] ?? null)) {
				$ignored[$key] = ['by' => $record['by'], 'at' => $record['at']];
			}
		}

		return $ignored;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function ignore(string $key, string $by, string $at): void
	{
		$this->change(static fn (array $ignored): array => [...$ignored, $key => ['by' => $by, 'at' => $at]]);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function unignore(string $key): void
	{
		$this->change(static fn (array $ignored): array => array_diff_key($ignored, [$key => true]));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function keep(array $keys): void
	{
		$this->change(static fn (array $ignored): array => array_intersect_key($ignored, array_flip($keys)));
	}

	/**
	 * Changes the records in a transaction, writing them sorted by key
	 * when they change, or removing the record when none are left.
	 *
	 * @param Closure(array<string, array{by: string, at: string}>): array<string, array{by: string, at: string}> $change
	 */
	private function change(Closure $change): void
	{
		$this->data->transaction(function () use ($change): void {
			$ignored = $this->all();
			$changed = $change($ignored);

			ksort($changed, SORT_STRING);

			if ($changed === $ignored) {
				return;
			}

			if ($changed === []) {
				$this->data->delete(self::RECORD);
			} else {
				$this->data->save(self::RECORD, $changed);
			}
		});
	}
}
