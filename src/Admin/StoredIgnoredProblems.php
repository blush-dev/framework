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
use Blush\Settings\InvalidSetting;
use Blush\Settings\SettingGroups;

/**
 * Keeps the ignored Site Health problems in Site Health's own group of
 * settings (`health`, D-613, D-673; `user/data/settings/health.json` for
 * files), under `ignored`: site data, kept with the site, not derived
 * from it as the last report is, and read only when Site Health asks. A
 * group that can't be read ignores nothing.
 */
final readonly class StoredIgnoredProblems implements IgnoredProblems
{
	/**
	 * The key the problems are kept under in the group.
	 */
	public const string KEY = 'ignored';

	public function __construct(
		private SettingGroups $groups
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function all(): array
	{
		try {
			$data = $this->groups->get(SettingGroups::HEALTH)[self::KEY] ?? [];
		} catch (InvalidSetting) {
			return [];
		}

		return self::problems($data);
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
	 * Changes the problems in a transaction, writing them sorted by key
	 * when they change, and leaving the group out when none are left.
	 *
	 * @param Closure(array<string, array{by: string, at: string}>): array<string, array{by: string, at: string}> $change
	 */
	private function change(Closure $change): void
	{
		$this->groups->update(SettingGroups::HEALTH, static function (array $group) use ($change): array {
			$ignored = self::problems($group[self::KEY] ?? []);
			$changed = $change($ignored);

			ksort($changed, SORT_STRING);

			if ($changed === $ignored) {
				return $group;
			}

			$group = array_diff_key($group, [self::KEY => true]);

			return $changed === [] ? $group : [...$group, self::KEY => $changed];
		});
	}

	/**
	 * The ignored problems a group holds, skipping any malformed.
	 *
	 * @return array<string, array{by: string, at: string}>
	 */
	private static function problems(mixed $data): array
	{
		$ignored = [];

		foreach (is_array($data) ? $data : [] as $key => $record) {
			if (is_string($key) && is_array($record) && is_string($record['by'] ?? null) && is_string($record['at'] ?? null)) {
				$ignored[$key] = ['by' => $record['by'], 'at' => $record['at']];
			}
		}

		return $ignored;
	}
}
