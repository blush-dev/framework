<?php

/**
 * Host format.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Export\Host;

/**
 * The built-in host file formats, keyed by the name `ExportConfig::$hosts`
 * uses (the "Type enum" of the enum + registry pattern, D-019).
 */
enum HostFormat: string
{
	case Apache  = 'apache';
	case Netlify = 'netlify';

	/**
	 * Returns the format's class.
	 *
	 * @return class-string<HostFiles>
	 */
	public function className(): string
	{
		return match ($this) {
			self::Apache  => ApacheFiles::class,
			self::Netlify => NetlifyFiles::class
		};
	}
}
