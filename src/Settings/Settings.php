<?php

/**
 * Settings.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Settings;

use Blush\Config\ConfigRepository;
use Blush\Config\InvalidConfig;

/**
 * The settings saved in the admin (D-324): only the ones the site owner
 * changed, each checked by its `Setting`. `apply()` lays them over the
 * config objects, so a saved setting wins over `config/` and `.env`.
 *
 * The `site` section holds the values of settings field sets add to the
 * Settings screens (D-343), by field name, as they were sent. The sets
 * load after the bootstrap reads this file, so those values are checked
 * by their fields where they're saved and read (`SiteSettings`), not
 * here.
 */
final readonly class Settings
{
	/**
	 * The section that holds field sets' settings.
	 */
	public const string SITE = 'site';

	/**
	 * @param array<string, mixed> $values Normalized values, keyed by `Setting` value.
	 * @param array<string, mixed> $site   Field sets' settings, by field name.
	 */
	private function __construct(
		private array $values = [],
		private array $site = []
	) {}

	/**
	 * No saved settings.
	 */
	public static function none(): self
	{
		return new self();
	}

	/**
	 * Builds the settings from the file's data: sections (`app`, `feed`,
	 * …) of keys to values, each checked.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws InvalidSetting When a section or key isn't a setting, or a value doesn't fit.
	 */
	public static function fromArray(array $data): self
	{
		$flat = [];
		$site = [];

		foreach ($data as $section => $values) {
			if (! is_array($values) || ($values !== [] && array_is_list($values))) {
				throw new InvalidSetting(sprintf('"%s" must be an object of settings.', $section));
			}

			if ($section === self::SITE) {
				$site = array_combine(array_map(strval(...), array_keys($values)), array_values($values));

				continue;
			}

			foreach ($values as $key => $value) {
				$setting = Setting::find((string) $section, (string) $key) ?? throw new InvalidSetting(sprintf('"%s.%s" isn\'t a setting the admin can change.', $section, $key));

				$flat[$setting->value] = $value;
			}
		}

		return self::none()->with($flat)->withSite($site);
	}

	/**
	 * Returns a copy with the given values, keyed by `Setting` value
	 * (`feed.limit`), each checked.
	 *
	 * @param  array<array-key, mixed> $changes
	 * @throws InvalidSetting When a key isn't a setting, or a value doesn't fit.
	 */
	public function with(array $changes): self
	{
		$values = $this->values;

		foreach ($changes as $key => $value) {
			$setting = Setting::tryFrom((string) $key) ?? throw new InvalidSetting(sprintf('"%s" isn\'t a setting the admin can change.', $key));

			$values[$setting->value] = $setting->normalize($value);
		}

		return new self(self::ordered($values), $this->site);
	}

	/**
	 * Returns a copy with field sets' settings set, by field name, as
	 * they're sent; `null` removes one. Their fields check them.
	 *
	 * @param array<string, mixed> $values
	 */
	public function withSite(array $values): self
	{
		$site = $this->site;

		foreach ($values as $name => $value) {
			if ($value === null) {
				unset($site[$name]);
			} else {
				$site[$name] = $value;
			}
		}

		ksort($site, SORT_STRING);

		return new self($this->values, $site);
	}

	/**
	 * Field sets' settings, by field name.
	 *
	 * @return array<string, mixed>
	 */
	public function site(): array
	{
		return $this->site;
	}

	/**
	 * Returns a copy without the given settings, so their config values
	 * are used again.
	 */
	public function without(Setting ...$settings): self
	{
		$values = $this->values;

		foreach ($settings as $setting) {
			unset($values[$setting->value]);
		}

		return new self($values, $this->site);
	}

	/**
	 * Whether the setting is saved.
	 */
	public function has(Setting $setting): bool
	{
		return array_key_exists($setting->value, $this->values);
	}

	/**
	 * The saved value, or `null` when it isn't saved.
	 */
	public function get(Setting $setting): mixed
	{
		return $this->values[$setting->value] ?? null;
	}

	/**
	 * Whether nothing is saved.
	 */
	public function isEmpty(): bool
	{
		return $this->values === [] && $this->site === [];
	}

	/**
	 * Lays the settings over the config objects they belong to. Each
	 * object is built again from its `toArray()` with the settings' keys
	 * replaced, so it's checked as a config file's would be.
	 *
	 * @throws InvalidSetting When a config object refuses a value.
	 */
	public function apply(ConfigRepository $config): ConfigRepository
	{
		$changes = [];

		foreach (Setting::cases() as $setting) {
			if ($this->has($setting)) {
				$changes[$setting->config()][$setting->configKey()] = $this->values[$setting->value];
			}
		}

		$objects = [];

		foreach ($changes as $class => $values) {
			$current = $config->find($class);

			try {
				$objects[] = $class::fromArray([...$current?->toArray() ?? [], ...$values]);
			} catch (InvalidConfig $error) {
				throw new InvalidSetting(sprintf('A saved setting doesn\'t fit: %s', $error->getMessage()), previous: $error);
			}
		}

		return $objects === [] ? $config : $config->with(...$objects);
	}

	/**
	 * The saved values as the file holds them: by section, then key, in
	 * the enum's order, then field sets' settings by name.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function toArray(): array
	{
		$sections = [];

		foreach (Setting::cases() as $setting) {
			if ($this->has($setting)) {
				$sections[$setting->section()][$setting->key()] = $this->values[$setting->value];
			}
		}

		if ($this->site !== []) {
			$sections[self::SITE] = $this->site;
		}

		return $sections;
	}

	/**
	 * The saved values, keyed by `Setting` value (`feed.limit`).
	 *
	 * @return array<string, mixed>
	 */
	public function values(): array
	{
		return $this->values;
	}

	/**
	 * Puts values in the enum's order, so the file reads the same way
	 * whatever order they were saved in.
	 *
	 * @param  array<string, mixed> $values
	 * @return array<string, mixed>
	 */
	private static function ordered(array $values): array
	{
		$ordered = [];

		foreach (Setting::cases() as $setting) {
			if (array_key_exists($setting->value, $values)) {
				$ordered[$setting->value] = $values[$setting->value];
			}
		}

		return $ordered;
	}
}
