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

use Blush\Console\Commands\ActivateTheme;
use Blush\Console\Commands\CacheClear;
use Blush\Console\Commands\CacheCompile;
use Blush\Console\Commands\CheckTheme;
use Blush\Console\Commands\CreateContent;
use Blush\Console\Commands\CreateTheme;
use Blush\Console\Commands\ExplainView;
use Blush\Console\Commands\Help;
use Blush\Console\Commands\IndexContent;
use Blush\Console\Commands\LintContent;
use Blush\Console\Commands\ListCommands;
use Blush\Console\Commands\ListContent;
use Blush\Console\Commands\ListThemes;
use Blush\Console\Commands\PublishMedia;
use Blush\Console\Commands\PublishThemes;
use Blush\Console\Commands\RoutesList;
use Blush\Console\Commands\Serve;

/**
 * The framework's own commands, keyed by name (the "Type enum" of the
 * enum + registry pattern, D-019).
 */
enum BuiltInCommand: string
{
	case List          = 'list';
	case Help          = 'help';
	case Serve         = 'serve';
	case CacheClear    = 'cache:clear';
	case CacheCompile  = 'cache:compile';
	case RoutesList    = 'routes:list';
	case ContentIndex  = 'content:index';
	case ContentLint   = 'content:lint';
	case ContentList   = 'content:list';
	case ContentNew    = 'content:new';
	case MediaPublish  = 'media:publish';
	case ThemeList     = 'theme:list';
	case ThemeActivate = 'theme:activate';
	case ThemeNew      = 'theme:new';
	case ThemeCheck    = 'theme:check';
	case ThemeWhy      = 'theme:why';
	case ThemePublish  = 'theme:publish';

	/**
	 * Returns the command's class.
	 *
	 * @return class-string
	 */
	public function className(): string
	{
		return match ($this) {
			self::List          => ListCommands::class,
			self::Help          => Help::class,
			self::Serve         => Serve::class,
			self::CacheClear    => CacheClear::class,
			self::CacheCompile  => CacheCompile::class,
			self::RoutesList    => RoutesList::class,
			self::ContentIndex  => IndexContent::class,
			self::ContentLint   => LintContent::class,
			self::ContentList   => ListContent::class,
			self::ContentNew    => CreateContent::class,
			self::MediaPublish  => PublishMedia::class,
			self::ThemeList     => ListThemes::class,
			self::ThemeActivate => ActivateTheme::class,
			self::ThemeNew      => CreateTheme::class,
			self::ThemeCheck    => CheckTheme::class,
			self::ThemeWhy      => ExplainView::class,
			self::ThemePublish  => PublishThemes::class
		};
	}
}
