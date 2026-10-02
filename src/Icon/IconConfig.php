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
 * Which installed icon packs are off (D-385), from `config/icons.php`.
 * Every installed pack is on unless it's named in `disabled`; the admin's
 * Icon Packs screen saves its own list in `user/data/settings.json`, over
 * this one.
 */
final readonly class IconConfig implements Config
{
	/**
	 * @param list<string> $disabled Packs to turn off, by name.
	 */
	public function __construct(
		public array $disabled = []
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data): static
	{
		$values = new ConfigValues($data, self::class);
		$values->assertKnownKeys(['disabled']);

		return new static(disabled: $values->stringList('disabled'));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function toArray(): array
	{
		return ['disabled' => $this->disabled];
	}
}
