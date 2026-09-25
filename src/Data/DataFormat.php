<?php

/**
 * Data formats.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Data;

/**
 * The built-in data file formats, keyed by file extension (the "Type enum"
 * of the enum + registry pattern, D-019). Case order is precedence: when a
 * name exists in more than one format, JSON wins (D-032).
 */
enum DataFormat: string
{
	case Json = 'json';
	case Yaml = 'yaml';
	case Yml  = 'yml';

	/**
	 * Returns the format's parser class.
	 *
	 * @return class-string<DataParser>
	 */
	public function parser(): string
	{
		return match ($this) {
			self::Json => JsonParser::class,
			self::Yaml,
			self::Yml  => YamlDataParser::class
		};
	}
}
