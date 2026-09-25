<?php

/**
 * Built-in commands.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console;

use Blush\Console\Commands\CacheClear;
use Blush\Console\Commands\CacheCompile;
use Blush\Console\Commands\Help;
use Blush\Console\Commands\ListCommands;
use Blush\Console\Commands\RoutesList;
use Blush\Console\Commands\Serve;

/**
 * The framework's own commands, keyed by name (the "Type enum" of the
 * enum + registry pattern, D-019).
 */
enum BuiltInCommand: string
{
	case List         = 'list';
	case Help         = 'help';
	case Serve        = 'serve';
	case CacheClear   = 'cache:clear';
	case CacheCompile = 'cache:compile';
	case RoutesList   = 'routes:list';

	/**
	 * Returns the command's class.
	 *
	 * @return class-string
	 */
	public function className(): string
	{
		return match ($this) {
			self::List         => ListCommands::class,
			self::Help         => Help::class,
			self::Serve        => Serve::class,
			self::CacheClear   => CacheClear::class,
			self::CacheCompile => CacheCompile::class,
			self::RoutesList   => RoutesList::class
		};
	}
}
