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
 * Which discovered plugins are enabled, from `config/plugins.php`.
 * By default every discovered plugin is enabled, since installing one is
 * the intent to use it. List names in `enabled` to allow only those, and in
 * `disabled` to switch individual ones off.
 */
final readonly class PluginConfig implements Config
{
	/**
	 * @param ?list<string> $enabled  Only these plugins, or `null` for all.
	 * @param list<string>  $disabled Plugins to switch off.
	 */
	public function __construct(
		public ?array $enabled = null,
		public array $disabled = []
	) {
	}

	/**
	 * Whether the named plugin is enabled.
	 */
	public function isEnabled(string $name): bool
	{
		return ! in_array($name, $this->disabled, true)
			&& ($this->enabled === null || in_array($name, $this->enabled, true));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data): static
	{
		$values = new ConfigValues($data, self::class);
		$values->assertKnownKeys(['enabled', 'disabled']);

		return new static(
			enabled: $values->nullableStringList('enabled'),
			disabled: $values->stringList('disabled')
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function toArray(): array
	{
		return [
			'enabled'  => $this->enabled,
			'disabled' => $this->disabled
		];
	}
}
