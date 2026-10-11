<?php

/**
 * Activity origin.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Activity;

/**
 * How a change came to be made (D-758): in the admin, through the MCP
 * server by an app, on the command line, or through the API.
 */
enum Via: string
{
	case Admin = 'admin';
	case Mcp   = 'mcp';
	case Cli   = 'cli';
	case Api   = 'api';

	/**
	 * Returns how the Activity tab names it, when no app's name does.
	 */
	public function label(): string
	{
		return match ($this) {
			self::Admin => 'Admin',
			self::Mcp   => 'MCP',
			self::Cli   => 'Command line',
			self::Api   => 'API'
		};
	}
}
