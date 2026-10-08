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
use Blush\Data\DataException;
use Blush\Data\DataStore;

/**
 * The settings saved in the admin (D-324, D-325), the `settings` record
 * in the data store (D-642; `user/data/settings.json` for files), in
 * sections named for the config files (`app`, `content`, `routes`,
 * `feed`, `sitemap`), each of the keys its config object takes:
 *
 *     {"app": {"name": "Field Notes"}, "feed": {"limit": 20}}
 *
 * It's data, not code (D-039), so it travels with the content. The
 * bootstrap reads it on every request, after the config (compiled or
 * not), so a save needs no compiling.
 *
 * No record is no settings. Saving nothing removes the record. Saves run
 * in a data store transaction, so two at once each keep the other's
 * changes.
 */
final readonly class SettingsStore
{
	/**
	 * The record's name in the data store.
	 */
	public const string RECORD = 'settings';

	public function __construct(
		private DataStore $data
	) {}

	/**
	 * Where the settings are kept, for people.
	 */
	public function location(): string
	{
		return $this->data->location(self::RECORD);
	}

	/**
	 * Reads the saved settings.
	 *
	 * @throws InvalidSetting When the record isn't a map of settings.
	 */
	public function read(): Settings
	{
		try {
			$data = $this->data->load(self::RECORD);
		} catch (DataException $error) {
			throw new InvalidSetting($error->getMessage(), previous: $error);
		}

		if ($data === null) {
			return Settings::none();
		}

		if ($data !== [] && array_is_list($data)) {
			throw new InvalidSetting(sprintf('%s must hold a JSON object of settings.', $this->location()));
		}

		try {
			return Settings::fromArray($data);
		} catch (InvalidSetting $error) {
			throw new InvalidSetting(sprintf('%s: %s', $this->location(), $error->getMessage()), previous: $error);
		}
	}

	/**
	 * Changes the saved settings in a transaction: reads them, passes them
	 * to `$change`, and writes what it returns.
	 *
	 * @param  Closure(Settings): Settings $change
	 * @throws InvalidSetting When a value doesn't fit or the settings can't be written.
	 */
	public function update(Closure $change): Settings
	{
		try {
			return $this->data->transaction(function () use ($change): Settings {
				$settings = $change($this->read());

				if ($settings->isEmpty()) {
					$this->data->delete(self::RECORD);
				} else {
					$this->data->save(self::RECORD, $settings->toArray());
				}

				return $settings;
			});
		} catch (DataException $error) {
			throw new InvalidSetting(sprintf('The settings couldn\'t be saved in %s.', $this->location()), previous: $error);
		}
	}
}
