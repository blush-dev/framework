<?php

/**
 * Writes and reads a scratch site's saved settings.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests;

use DirectoryIterator;
use Blush\Settings\SettingGroups;

/**
 * The saved settings as files keep them (D-673): a group a file,
 * `user/data/settings/{key}.json`, its `/` as `__`.
 */
trait SavedSettings
{
	use TemporaryDirectory;

	/**
	 * Writes groups of settings, by group, from JSON as the old single file
	 * held them: `{"app": {"name": "Blog"}, "feed": {"limit": 5}}`.
	 */
	private function writeSettings(string $json, ?string $root = null): void
	{
		$groups = json_decode($json, true);
		$folder = ($root ?? $this->temporaryDirectory()) . '/user/data/settings';

		if (! is_dir($folder)) {
			mkdir($folder, 0777, true);
		}

		foreach (is_array($groups) ? $groups : [] as $group => $values) {
			file_put_contents("{$folder}/" . SettingGroups::key((string) $group) . '.json', json_encode($values, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
		}
	}

	/**
	 * Reads the saved groups back, by group, without their ids, sorted;
	 * none when nothing's saved.
	 *
	 * @return array<string, mixed>
	 */
	private function savedSettings(?string $root = null): array
	{
		$folder = ($root ?? $this->temporaryDirectory()) . '/user/data/settings';
		$groups = [];

		if (! is_dir($folder)) {
			return [];
		}

		foreach (new DirectoryIterator($folder) as $file) {
			if ($file->isFile() && $file->getExtension() === 'json') {
				$values = json_decode((string) file_get_contents($file->getPathname()), true);

				$groups[SettingGroups::name($file->getBasename('.json'))] = is_array($values) ? array_diff_key($values, ['id' => true]) : $values;
			}
		}

		ksort($groups);

		return $groups;
	}
}
