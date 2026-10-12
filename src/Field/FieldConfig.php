<?php

/**
 * Field configuration.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Field;

use Override;
use Blush\Config\Config;
use Blush\Config\ConfigValues;

/**
 * The site's field settings, from `config/fields.php` (D-337). Fields
 * aren't made here (D-791): `config/` configures, and field sets are the
 * site's, made in the admin (`user/data/fields`), read unless `dataSets`
 * is off:
 *
 *     return new FieldConfig(dataSets: false);
 */
final readonly class FieldConfig implements Config
{
	/**
	 * @param bool $dataSets Whether `user/data/fields` is read.
	 */
	public function __construct(public bool $dataSets = true)
	{}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data): static
	{
		$values = new ConfigValues($data, self::class);
		$values->assertKnownKeys(['dataSets']);

		return new static($values->bool('dataSets', true));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function toArray(): array
	{
		return ['dataSets' => $this->dataSets];
	}
}
