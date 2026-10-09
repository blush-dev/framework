<?php

/**
 * Settings store.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Settings;

use Closure;

/**
 * The settings saved in the admin (D-324, D-325): core's groups of
 * `SettingGroups` (D-673), one a section named for its config file (`app`,
 * `content`, `routes`, `feed`, `sitemap`, …), each of the keys its config
 * object takes, and `site`, field sets' settings. On files, each group is
 * its own file, `user/data/settings/{section}.json`:
 *
 *     user/data/settings/app.json:  {"name": "Field Notes"}
 *     user/data/settings/feed.json: {"limit": 20}
 *
 * It's data, not code (D-039), so it travels with the content. The
 * bootstrap reads the boot groups on every request, after the config
 * (compiled or not), and each other group when its config object is
 * first asked for, so a save needs no compiling.
 *
 * No group is no settings in it. A group left empty is removed. Saves run
 * in a transaction, so two at once each keep the other's changes, and
 * only the groups that changed are written.
 */
final readonly class SettingsStore
{
	public function __construct(
		private SettingGroups $groups
	) {}

	/**
	 * Where a section's settings are kept, for people:
	 * `user/data/settings/theme.json` for a file.
	 */
	public function location(string $section): string
	{
		return $this->groups->location($section);
	}

	/**
	 * Reads every saved setting.
	 *
	 * @throws InvalidSetting When a group isn't a map of settings.
	 */
	public function read(): Settings
	{
		return $this->settings($this->groups->all());
	}

	/**
	 * Changes the saved settings in a transaction: reads them, passes them
	 * to `$change`, and writes the groups that changed.
	 *
	 * @param  Closure(Settings): Settings $change
	 * @throws InvalidSetting When a value doesn't fit or the settings can't be written.
	 */
	public function update(Closure $change): Settings
	{
		return $this->groups->transaction(function () use ($change): Settings {
			$before   = $this->read();
			$settings = $change($before);
			$old      = $before->toArray();
			$new      = $settings->toArray();

			foreach (array_keys([...$old, ...$new]) as $section) {
				if (($old[$section] ?? []) !== ($new[$section] ?? [])) {
					$this->groups->save($section, $new[$section] ?? []);
				}
			}

			return $settings;
		});
	}

	/**
	 * Builds the settings from core's groups, by section.
	 *
	 * @param  array<string, array<string, mixed>> $groups
	 * @throws InvalidSetting
	 */
	private function settings(array $groups): Settings
	{
		$sections = array_intersect_key($groups, array_flip([...Setting::sections(), Settings::SITE]));

		foreach ($sections as $section => $values) {
			try {
				Settings::fromArray([$section => $values]);
			} catch (InvalidSetting $error) {
				throw new InvalidSetting(sprintf('%s: %s', $this->location($section), $error->getMessage()), previous: $error);
			}
		}

		return Settings::fromArray($sections);
	}
}
