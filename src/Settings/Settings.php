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
 */
final readonly class Settings
{
	/**
	 * @param array<string, mixed> $values Normalized values, keyed by `Setting` value.
	 */
	private function __construct(
		private array $values = []
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

		foreach ($data as $section => $values) {
			if (! is_array($values) || ($values !== [] && array_is_list($values))) {
				throw new InvalidSetting(sprintf('"%s" must be an object of settings.', $section));
			}

			foreach ($values as $key => $value) {
				$setting = Setting::find((string) $section, (string) $key) ?? throw new InvalidSetting(sprintf('"%s.%s" isn\'t a setting the admin can change.', $section, $key));

				$flat[$setting->value] = $value;
			}
		}

		return self::none()->with($flat);
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

		return new self(self::ordered($values));
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

		return new self($values);
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
		return $this->values === [];
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
				$changes[$setting->config()][$setting->key()] = $this->values[$setting->value];
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
	 * the enum's order.
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
