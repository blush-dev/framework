<?php

/**
 * Config interface.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Config;

/**
 * A typed, immutable configuration object (D-017). Config files in `config/`
 * return these, built with named arguments:
 *
 *     return new AppConfig(
 *         name: 'My Site',
 *         url: $env->string('APP_URL'),
 *         timezone: 'America/Chicago'
 *     );
 *
 * `fromArray()` builds one from array, JSON, YAML, or env-sourced data, and
 * `toArray()` is its inverse. The pair is also how the merged config is
 * compiled to a PHP file and read back, so `toArray()` must return only
 * scalars, `null`, enum cases, and arrays of those.
 */
interface Config
{
	/**
	 * Builds the config from an array, validating every value.
	 *
	 * @param  array<array-key, mixed> $data
	 * @throws InvalidConfig
	 */
	public static function fromArray(array $data): static;

	/**
	 * Returns the config as an array `fromArray()` accepts.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array;
}
