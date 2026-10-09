<?php

/**
 * Plugin config.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Plugin;

use Override;
use Blush\Config\Config;
use Blush\Config\ConfigValues;

/**
 * Which plugins are on (D-390, D-391). Config only ever says what is on,
 * never what is off.
 *
 * - **By default,** a plugin Composer installed is on, and a local one
 *   (in `extensions/`) is on only when `config/plugins.php` names it in
 *   `enabled`.
 * - **Once the admin saves a list** (`plugins.enabled` in
 *   the saved settings, laid over `saved`), that list is all of
 *   what's on, Composer plugins included: one it doesn't name is off,
 *   even one installed after it was saved.
 */
final readonly class PluginConfig implements Config
{
	/**
	 * @param list<string>  $enabled Local plugins to turn on, by name.
	 * @param ?list<string> $saved   The admin's list of every plugin that's on, or `null` when it hasn't saved one.
	 */
	public function __construct(
		public array $enabled = [],
		public ?array $saved = null
	) {}

	/**
	 * Whether a plugin is turned on.
	 */
	public function isEnabled(PluginManifest $plugin): bool
	{
		return $this->turnsOn($plugin->name, $plugin->source);
	}

	/**
	 * Whether a plugin, by its name and where it comes from, is turned
	 * on: one that's broken (D-394) is asked about this way.
	 */
	public function turnsOn(string $name, PluginSource $source): bool
	{
		return $this->saved === null
			? $source === PluginSource::Composer || in_array($name, $this->enabled, true)
			: in_array($name, $this->saved, true);
	}

	/**
	 * A copy with a plugin turned on as well (D-440): added to the
	 * admin's saved list, or, without one, to config's.
	 */
	public function with(string $name): self
	{
		return $this->saved !== null
			? new self($this->enabled, [...$this->saved, $name])
			: new self([...$this->enabled, $name]);
	}

	/**
	 * The plugins named as on by the list in use: the admin's, or the
	 * config file's.
	 *
	 * @return list<string>
	 */
	public function named(): array
	{
		return $this->saved ?? $this->enabled;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data): static
	{
		$values = new ConfigValues($data, self::class);
		$values->assertKnownKeys(['enabled', 'saved']);

		return new static(
			enabled: $values->stringList('enabled'),
			saved: $values->nullableStringList('saved')
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function toArray(): array
	{
		return ['enabled' => $this->enabled, 'saved' => $this->saved];
	}
}
