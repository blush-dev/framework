<?php

/**
 * Settings file.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Settings;

use Closure;
use JsonException;
use Throwable;
use Blush\Core\Paths;
use Blush\Data\DataLoader;
use Blush\Support\Filesystem;

/**
 * `user/data/settings.json` (D-324, D-325): the settings saved in the
 * admin, in sections named for the config files (`app`, `content`,
 * `routes`, `feed`, `sitemap`), each of the keys its config object takes:
 *
 *     {"app": {"name": "Field Notes"}, "feed": {"limit": 20}}
 *
 * It's data, not code (D-039), so it lives in `user/` and travels with
 * the content. The bootstrap reads it on every request, after the config
 * (compiled or not), so a save needs no compiling.
 *
 * A missing file is no settings. Saving nothing removes the file. Writes
 * are atomic, under `storage/cache/settings.lock`, so two saves at once
 * each keep the other's changes.
 */
final readonly class SettingsFile
{
	/**
	 * The file's name in `user/data`.
	 */
	public const string FILE = 'settings.json';

	public function __construct(
		private Paths $paths,
		private Filesystem $filesystem = new Filesystem()
	) {}

	/**
	 * The file's path.
	 */
	public function path(): string
	{
		return "{$this->paths->data}/" . self::FILE;
	}

	/**
	 * Reads the saved settings.
	 *
	 * @throws InvalidSetting When the file isn't a JSON object of settings.
	 */
	public function read(): Settings
	{
		$path = $this->path();

		if (! is_file($path)) {
			return Settings::none();
		}

		$json = @file_get_contents($path);

		try {
			$data = $json === false ? null : json_decode($json, true, 16, JSON_THROW_ON_ERROR);
		} catch (JsonException $error) {
			throw new InvalidSetting(sprintf('%s isn\'t valid JSON: %s.', $this->paths->relative($path), $error->getMessage()), previous: $error);
		}

		if (! is_array($data) || ($data !== [] && array_is_list($data))) {
			throw new InvalidSetting(sprintf('%s must hold a JSON object of settings.', $this->paths->relative($path)));
		}

		// An editor's JSON Schema pointer isn't a setting (D-491).
		unset($data[DataLoader::SCHEMA]);

		try {
			return Settings::fromArray($data);
		} catch (InvalidSetting $error) {
			throw new InvalidSetting(sprintf('%s: %s', $this->paths->relative($path), $error->getMessage()), previous: $error);
		}
	}

	/**
	 * Changes the saved settings under the lock: reads them, passes them
	 * to `$change`, and writes what it returns.
	 *
	 * @param  Closure(Settings): Settings $change
	 * @throws InvalidSetting When a value doesn't fit or the file can't be written.
	 */
	public function update(Closure $change): Settings
	{
		return $this->locked(function () use ($change): Settings {
			$settings = $change($this->read());

			$this->write($settings);

			return $settings;
		});
	}

	/**
	 * Writes the settings, or removes the file when there are none.
	 *
	 * @throws InvalidSetting
	 */
	private function write(Settings $settings): void
	{
		$path = $this->path();

		try {
			if ($settings->isEmpty()) {
				if (is_file($path) && ! @unlink($path)) {
					throw new InvalidSetting('unable to remove it');
				}

				return;
			}

			$this->filesystem->writeAtomic($path, json_encode([...$this->schema(), ...$settings->toArray()], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
		} catch (Throwable $error) {
			throw new InvalidSetting(sprintf('The settings couldn\'t be saved in %s.', $this->paths->relative($path)), previous: $error);
		}
	}

	/**
	 * The file's `$schema` key, kept first when the settings are written.
	 *
	 * @return array<string, string>
	 */
	private function schema(): array
	{
		$json = @file_get_contents($this->path());
		$data = $json === false ? null : json_decode($json, true, 16);

		return is_array($data) && is_string($data[DataLoader::SCHEMA] ?? null) ? [DataLoader::SCHEMA => $data[DataLoader::SCHEMA]] : [];
	}

	/**
	 * Runs a change under the settings lock.
	 *
	 * @template T
	 * @param  Closure(): T $change
	 * @return T
	 * @throws InvalidSetting
	 */
	private function locked(Closure $change): mixed
	{
		if (! is_dir($this->paths->cache)) {
			@mkdir($this->paths->cache, 0775, true);
		}

		$lock = @fopen("{$this->paths->cache}/settings.lock", 'c');

		if ($lock === false || ! flock($lock, LOCK_EX)) {
			throw new InvalidSetting('Unable to take the settings lock.');
		}

		try {
			return $change();
		} finally {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
	}
}
