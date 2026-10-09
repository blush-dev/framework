<?php

/**
 * Icon config.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Icon;

use Override;
use Blush\Config\Config;
use Blush\Config\ConfigValues;

/**
 * Which icon packs are on (D-385, D-390, D-391), as `PluginConfig` says
 * which plugins are: by default, a pack Composer installed is on, and a
 * local one (in `extensions/`) only when `config/icons.php` names it in
 * `enabled`; once the admin's Icon Packs screen saves a list
 * (`icons.enabled` in the saved settings, laid over `saved`), that
 * list is all of what's on. Config only ever says what is on.
 */
final readonly class IconConfig implements Config
{
	/**
	 * @param list<string>  $enabled Local packs to turn on, by name.
	 * @param ?list<string> $saved   The admin's list of every pack that's on, or `null` when it hasn't saved one.
	 */
	public function __construct(
		public array $enabled = [],
		public ?array $saved = null
	) {}

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
