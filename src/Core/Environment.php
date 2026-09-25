<?php

/**
 * Environment.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Core;

/**
 * The environment a site runs in, read from `APP_ENV` (D-045). Debug output is
 * a separate flag (`APP_DEBUG`), so a staging site can run without it.
 */
enum Environment: string
{
	case Development = 'development';
	case Staging     = 'staging';
	case Production  = 'production';

	/**
	 * Parses an `APP_ENV` value, accepting common short forms (`dev`,
	 * `local`, `stage`, `prod`). Anything unrecognized is an error rather
	 * than silently becoming production or development.
	 *
	 * @throws InvalidEnvironment
	 */
	public static function fromString(string $value): self
	{
		return match (strtolower(trim($value))) {
			'development', 'dev', 'local' => self::Development,
			'staging', 'stage'            => self::Staging,
			'production', 'prod'          => self::Production,
			default => throw new InvalidEnvironment(sprintf(
				'"%s" is not a valid environment. Use development, staging, or production.',
				$value
			))
		};
	}

	/**
	 * Whether this is the development environment.
	 */
	public function isDevelopment(): bool
	{
		return $this === self::Development;
	}

	/**
	 * Whether this is the production environment.
	 */
	public function isProduction(): bool
	{
		return $this === self::Production;
	}
}
