<?php

/**
 * Setting groups.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Settings;

use Closure;
use Throwable;
use Psr\Clock\ClockInterface;
use Blush\Core\Paths;
use Blush\Extension\ExtensionName;
use Blush\Storage\File\FileLayout;
use Blush\Storage\Record\KeyedTable;
use Blush\Storage\Record\RecordException;
use Blush\Storage\Record\RecordStore;
use Blush\Storage\Record\RecordStores;
use Blush\Storage\Record\Table;
use Blush\Storage\StorageArea;

/**
 * The site's saved settings, a group at a time (D-670, D-673): a record
 * per group in the `settings` table, its fields the group's settings.
 *
 * - **Groups:** core's sections (`app`, `feed`, `theme`, …, see
 *   `Setting`), the site's own field-set settings (`site`), Site Health's
 *   (`health`), and any extension's or theme's, by its name
 *   (`acme/gallery`), whose values it checks itself.
 * - **Names:** a group's `/` is stored as `__` (`acme__gallery`), which
 *   no extension name holds, so a name is the table's key. On files, a
 *   group is `user/data/settings/{key}.json`.
 * - **Loading:** the boot groups (`BOOT`, what decides what boots) are
 *   read together by the bootstrap, before the container; any other group
 *   is read when it's first asked for, then kept for the request. Writes
 *   read the group again in their transaction, so they never act on what
 *   was kept.
 */
final class SettingGroups
{
	/**
	 * The table's name, and its folder under `user/data`.
	 */
	public const string TABLE = 'settings';

	/**
	 * The groups the bootstrap reads before the container: the site's
	 * language and time, and the theme, plugins, and icon packs that boot.
	 */
	public const array BOOT = ['app', 'theme', 'plugins', 'icons'];

	/**
	 * Site Health's group.
	 */
	public const string HEALTH = 'health';

	/**
	 * Groups read so far, by name.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $read = [];

	public function __construct(
		private readonly RecordStores|RecordStore $stores,
		private readonly ClockInterface $clock
	) {}

	/**
	 * The settings' table.
	 */
	public static function table(): Table
	{
		return new Table(self::TABLE, StorageArea::Data, key: 'group', fields: ['group']);
	}

	/**
	 * How the filesystem driver keeps the table: a file a group,
	 * `user/data/settings/{key}.json`, the key only in its name.
	 */
	public static function layout(Paths $paths): FileLayout
	{
		return FileLayout::folder("{$paths->data}/" . self::TABLE, keyInName: true);
	}

	/**
	 * Whether a name can be a group's: lowercase letters, digits, `_`,
	 * and `-`, or an extension's `vendor/name`.
	 */
	public static function isName(string $name): bool
	{
		return preg_match('/\A[a-z][a-z0-9-]*(?:_[a-z0-9-]+)*\z/', $name) === 1 || ExtensionName::isValid($name);
	}

	/**
	 * A group's key in the table: its name with `__` for its `/`.
	 *
	 * @throws InvalidSetting When the name can't be a group's.
	 */
	public static function key(string $group): string
	{
		return self::isName($group)
			? str_replace('/', '__', $group)
			: throw new InvalidSetting(sprintf('"%s" can\'t name a group of settings: use lowercase letters, digits, "_", and "-", or an extension\'s vendor/name.', $group));
	}

	/**
	 * A group's name from its key in the table.
	 */
	public static function name(string $key): string
	{
		return str_replace('__', '/', $key);
	}

	/**
	 * Returns a group's settings, empty when none are saved.
	 *
	 * @return array<string, mixed>
	 * @throws InvalidSetting When the name won't do or the group can't be read.
	 */
	public function get(string $group): array
	{
		return $this->read[$group] ??= $this->fresh($group);
	}

	/**
	 * Returns the boot groups' settings, by name, read together.
	 *
	 * @return array<string, array<string, mixed>>
	 * @throws InvalidSetting When they can't be read.
	 */
	public function boot(): array
	{
		$groups = [];

		foreach (self::BOOT as $group) {
			$groups[$group] = $this->get($group);
		}

		return $groups;
	}

	/**
	 * Returns every saved group, by name: for copying, the admin, and the
	 * screens that show every setting.
	 *
	 * @return array<string, array<string, mixed>>
	 * @throws InvalidSetting When they can't be read.
	 */
	public function all(): array
	{
		try {
			$all = $this->records()->all();
		} catch (RecordException $error) {
			throw new InvalidSetting(sprintf('The settings can\'t be read: %s', $error->getMessage()), previous: $error);
		}

		$groups = [];

		foreach ($all as $key => $values) {
			$groups[self::name($key)] = $values;
		}

		return $this->read = [...$this->read, ...$groups];
	}

	/**
	 * Saves a group's settings whole; none removes the group.
	 *
	 * @param  array<string, mixed> $values
	 * @throws InvalidSetting When the name won't do or the group can't be written.
	 */
	public function save(string $group, array $values): void
	{
		$key = self::key($group);

		try {
			if ($values === []) {
				$this->records()->delete($key);
			} else {
				$this->records()->save($key, $values);
			}
		} catch (RecordException $error) {
			throw new InvalidSetting(sprintf('The "%s" settings couldn\'t be saved in %s: %s', $group, $this->location($group), $error->getMessage()), previous: $error);
		}

		$this->read[$group] = $values;
	}

	/**
	 * Changes a group's settings in a transaction: reads them again,
	 * passes them to `$change`, and saves what it returns when it differs.
	 *
	 * @param  Closure(array<string, mixed>): array<string, mixed> $change
	 * @return array<string, mixed> What was saved.
	 * @throws InvalidSetting When the group can't be read or written.
	 */
	public function update(string $group, Closure $change): array
	{
		return $this->transaction(function () use ($group, $change): array {
			$current = $this->fresh($group);
			$changed = $change($current);

			if ($changed !== $current) {
				$this->save($group, $changed);
			}

			return $changed;
		});
	}

	/**
	 * Runs `$write` in a transaction of the table's store: what it saved
	 * is put back when it throws.
	 *
	 * @template T
	 * @param  Closure(): T $write
	 * @return T
	 * @throws InvalidSetting When the store can't be locked.
	 */
	public function transaction(Closure $write): mixed
	{
		try {
			return $this->records()->transaction($write);
		} catch (RecordException $error) {
			$this->read = [];

			throw new InvalidSetting(sprintf('The settings couldn\'t be saved: %s', $error->getMessage()), previous: $error);
		} catch (Throwable $error) {
			// What was put back may differ from what was kept.
			$this->read = [];

			throw $error;
		}
	}

	/**
	 * Where a group is kept, for people: `user/data/settings/feed.json`
	 * for a file.
	 */
	public function location(string $group): string
	{
		return $this->records()->location(self::isName($group) ? self::key($group) : $group);
	}

	/**
	 * Reads a group from the table, past what was kept.
	 *
	 * @return array<string, mixed>
	 * @throws InvalidSetting
	 */
	private function fresh(string $group): array
	{
		try {
			return $this->records()->find(self::key($group)) ?? [];
		} catch (RecordException $error) {
			throw new InvalidSetting(sprintf('The "%s" settings can\'t be read: %s', $group, $error->getMessage()), previous: $error);
		}
	}

	/**
	 * The settings' table, as data by key.
	 */
	private function records(): KeyedTable
	{
		return new KeyedTable($this->stores, self::table(), $this->clock);
	}
}
