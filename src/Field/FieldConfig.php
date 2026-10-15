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
 * aren't made here (D-791): `config/` configures, and the field library
 * is the site's, made in the admin (`user/data/fields`, D-858), read
 * unless `library` is off:
 *
 *     return new FieldConfig(library: false);
 */
final readonly class FieldConfig implements Config
{
	/**
	 * @param bool $library Whether the field library (`user/data/fields`) is read.
	 */
	public function __construct(public bool $library = true)
	{}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data): static
	{
		$values = new ConfigValues($data, self::class);
		$values->assertKnownKeys(['library']);

		return new static($values->bool('library', true));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function toArray(): array
	{
		return ['library' => $this->library];
	}
}
