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
use Blush\Console\Commands\AddAccount;
use Blush\Console\Commands\Build;
use Blush\Console\Commands\CacheClear;
use Blush\Console\Commands\CacheCompile;
use Blush\Console\Commands\CheckSite;
use Blush\Console\Commands\CheckTheme;
use Blush\Console\Commands\CreateContent;
use Blush\Console\Commands\CreateTheme;
use Blush\Console\Commands\ExplainView;
use Blush\Console\Commands\Help;
use Blush\Console\Commands\IndexContent;
use Blush\Console\Commands\IndexMedia;
use Blush\Console\Commands\LintContent;
use Blush\Console\Commands\ListAccounts;
use Blush\Console\Commands\ListCommands;
use Blush\Console\Commands\ListComponents;
use Blush\Console\Commands\ListIcons;
use Blush\Console\Commands\ListMenus;
use Blush\Console\Commands\ListContent;
use Blush\Console\Commands\ListThemes;
use Blush\Console\Commands\PreviewContent;
use Blush\Console\Commands\Publish;
use Blush\Console\Commands\PublishMedia;
use Blush\Console\Commands\PublishThemes;
use Blush\Console\Commands\ReinstateAccount;
use Blush\Console\Commands\RemoveAccount;
use Blush\Console\Commands\RoutesList;
use Blush\Console\Commands\RunSchedule;
use Blush\Console\Commands\Serve;
use Blush\Console\Commands\SetAccountAuthor;
use Blush\Console\Commands\SetAccountPassword;
use Blush\Console\Commands\SetAccountRoles;
use Blush\Console\Commands\SetUpSite;
use Blush\Console\Commands\ShowMenu;
use Blush\Console\Commands\SuspendAccount;

/**
 * The framework's own commands, keyed by name (the "Type enum" of the
 * enum + registry pattern, D-019).
 */
enum BuiltInCommand: string
{
	case List          = 'list';
	case Help          = 'help';
	case Init          = 'init';
	case Doctor        = 'doctor';
	case Serve         = 'serve';
	case CacheClear    = 'cache:clear';
	case CacheCompile  = 'cache:compile';
	case RoutesList    = 'routes:list';
	case ContentIndex  = 'content:index';
	case ContentLint   = 'content:lint';
	case ContentList   = 'content:list';
	case ContentNew    = 'content:new';
	case ContentPreview = 'content:preview';
	case MediaIndex    = 'media:index';
	case MediaPublish  = 'media:publish';
	case ThemeList     = 'theme:list';
	case ThemeActivate = 'theme:activate';
	case ThemeNew      = 'theme:new';
	case ThemeCheck    = 'theme:check';
	case ThemeWhy      = 'theme:why';
	case ThemePublish  = 'theme:publish';
	case ComponentList = 'component:list';
	case IconList      = 'icon:list';
	case MenuList      = 'menu:list';
	case MenuShow      = 'menu:show';
	case Publish       = 'publish';
	case ScheduleRun   = 'schedule:run';
	case Build         = 'build';
	case AccountAdd       = 'account:add';
	case AccountList      = 'account:list';
	case AccountPassword  = 'account:password';
	case AccountRoles     = 'account:roles';
	case AccountAuthor    = 'account:author';
	case AccountSuspend   = 'account:suspend';
	case AccountReinstate = 'account:reinstate';
	case AccountRemove    = 'account:remove';

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
			self::Init          => SetUpSite::class,
			self::Doctor        => CheckSite::class,
			self::Serve         => Serve::class,
			self::CacheClear    => CacheClear::class,
			self::CacheCompile  => CacheCompile::class,
			self::RoutesList    => RoutesList::class,
			self::ContentIndex  => IndexContent::class,
			self::ContentLint   => LintContent::class,
			self::ContentList   => ListContent::class,
			self::ContentNew    => CreateContent::class,
			self::ContentPreview => PreviewContent::class,
			self::MediaIndex    => IndexMedia::class,
			self::MediaPublish  => PublishMedia::class,
			self::ThemeList     => ListThemes::class,
			self::ThemeActivate => ActivateTheme::class,
			self::ThemeNew      => CreateTheme::class,
			self::ThemeCheck    => CheckTheme::class,
			self::ThemeWhy      => ExplainView::class,
			self::ThemePublish  => PublishThemes::class,
			self::ComponentList => ListComponents::class,
			self::IconList      => ListIcons::class,
			self::MenuList      => ListMenus::class,
			self::MenuShow      => ShowMenu::class,
			self::Publish       => Publish::class,
			self::ScheduleRun   => RunSchedule::class,
			self::Build         => Build::class,
			self::AccountAdd       => AddAccount::class,
			self::AccountList      => ListAccounts::class,
			self::AccountPassword  => SetAccountPassword::class,
			self::AccountRoles     => SetAccountRoles::class,
			self::AccountAuthor    => SetAccountAuthor::class,
			self::AccountSuspend   => SuspendAccount::class,
			self::AccountReinstate => ReinstateAccount::class,
			self::AccountRemove    => RemoveAccount::class
		};
	}
}
