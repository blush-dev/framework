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
use Blush\Console\Commands\CacheClear;
use Blush\Console\Commands\CacheCompile;
use Blush\Console\Commands\ChangeRelation;
use Blush\Console\Commands\CheckIconPacks;
use Blush\Console\Commands\CheckPlugins;
use Blush\Console\Commands\CheckSite;
use Blush\Console\Commands\CheckTheme;
use Blush\Console\Commands\CreateContent;
use Blush\Console\Commands\CreateMissingTerms;
use Blush\Console\Commands\CreatePlugin;
use Blush\Console\Commands\CreateTheme;
use Blush\Console\Commands\ExplainView;
use Blush\Console\Commands\Help;
use Blush\Console\Commands\MigrateTaxonomies;
use Blush\Console\Commands\RenameToPattern;
use Blush\Console\Commands\FileRefs;
use Blush\Console\Commands\FixIds;
use Blush\Console\Commands\FlattenCollections;
use Blush\Console\Commands\FixMediaIds;
use Blush\Console\Commands\IndexContent;
use Blush\Console\Commands\IndexMedia;
use Blush\Console\Commands\LintContent;
use Blush\Console\Commands\ListAccounts;
use Blush\Console\Commands\ListCommands;
use Blush\Console\Commands\ListComponents;
use Blush\Console\Commands\ListDirectives;
use Blush\Console\Commands\ListIcons;
use Blush\Console\Commands\ListMenus;
use Blush\Console\Commands\ListPlugins;
use Blush\Console\Commands\ListContent;
use Blush\Console\Commands\ListThemes;
use Blush\Console\Commands\PreviewContent;
use Blush\Console\Commands\Publish;
use Blush\Console\Commands\PublishMedia;
use Blush\Console\Commands\RecordMediaSizes;
use Blush\Console\Commands\PublishThemes;
use Blush\Console\Commands\ReinstateAccount;
use Blush\Console\Commands\RemoveAccount;
use Blush\Console\Commands\RoutesList;
use Blush\Console\Commands\RunSchedule;
use Blush\Console\Commands\Serve;
use Blush\Console\Commands\SetAccountAuthor;
use Blush\Console\Commands\SetAccountEmail;
use Blush\Console\Commands\SetAccountName;
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
	case ContentFileNames = 'content:filenames';
	case ContentFlatten = 'content:flatten';
	case ContentIds    = 'content:ids';
	case ContentIndex  = 'content:index';
	case ContentLint   = 'content:lint';
	case ContentList   = 'content:list';
	case ContentNew    = 'content:new';
	case ContentPreview = 'content:preview';
	case ContentRefs   = 'content:refs';
	case ContentRelation = 'content:relation';
	case ContentTerms  = 'content:terms';
	case ContentTaxonomies = 'content:taxonomies';
	case MediaIds      = 'media:ids';
	case MediaIndex    = 'media:index';
	case MediaPublish  = 'media:publish';
	case MediaSizes    = 'media:sizes';
	case ThemeList     = 'theme:list';
	case ThemeActivate = 'theme:activate';
	case ThemeNew      = 'theme:new';
	case ThemeCheck    = 'theme:check';
	case ThemeWhy      = 'theme:why';
	case ThemePublish  = 'theme:publish';
	case PluginList    = 'plugin:list';
	case PluginCheck   = 'plugin:check';
	case PluginNew     = 'plugin:new';
	case DirectiveList = 'directive:list';
	case ComponentList = 'component:list';
	case IconList      = 'icon:list';
	case IconPackCheck = 'icon-pack:check';
	case MenuList      = 'menu:list';
	case MenuShow      = 'menu:show';
	case Publish       = 'publish';
	case ScheduleRun   = 'schedule:run';
	case AccountAdd       = 'account:add';
	case AccountList      = 'account:list';
	case AccountPassword  = 'account:password';
	case AccountRoles     = 'account:roles';
	case AccountAuthor    = 'account:author';
	case AccountName      = 'account:name';
	case AccountEmail     = 'account:email';
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
			self::ContentFileNames => RenameToPattern::class,
			self::ContentFlatten => FlattenCollections::class,
			self::ContentIds    => FixIds::class,
			self::ContentIndex  => IndexContent::class,
			self::ContentLint   => LintContent::class,
			self::ContentList   => ListContent::class,
			self::ContentNew    => CreateContent::class,
			self::ContentPreview => PreviewContent::class,
			self::ContentRefs   => FileRefs::class,
			self::ContentRelation => ChangeRelation::class,
			self::ContentTerms  => CreateMissingTerms::class,
			self::ContentTaxonomies => MigrateTaxonomies::class,
			self::MediaIds      => FixMediaIds::class,
			self::MediaIndex    => IndexMedia::class,
			self::MediaPublish  => PublishMedia::class,
			self::MediaSizes    => RecordMediaSizes::class,
			self::ThemeList     => ListThemes::class,
			self::ThemeActivate => ActivateTheme::class,
			self::ThemeNew      => CreateTheme::class,
			self::ThemeCheck    => CheckTheme::class,
			self::ThemeWhy      => ExplainView::class,
			self::ThemePublish  => PublishThemes::class,
			self::PluginList    => ListPlugins::class,
			self::PluginCheck   => CheckPlugins::class,
			self::PluginNew     => CreatePlugin::class,
			self::DirectiveList => ListDirectives::class,
			self::ComponentList => ListComponents::class,
			self::IconList      => ListIcons::class,
			self::IconPackCheck => CheckIconPacks::class,
			self::MenuList      => ListMenus::class,
			self::MenuShow      => ShowMenu::class,
			self::Publish       => Publish::class,
			self::ScheduleRun   => RunSchedule::class,
			self::AccountAdd       => AddAccount::class,
			self::AccountList      => ListAccounts::class,
			self::AccountPassword  => SetAccountPassword::class,
			self::AccountRoles     => SetAccountRoles::class,
			self::AccountAuthor    => SetAccountAuthor::class,
			self::AccountName      => SetAccountName::class,
			self::AccountEmail     => SetAccountEmail::class,
			self::AccountSuspend   => SuspendAccount::class,
			self::AccountReinstate => ReinstateAccount::class,
			self::AccountRemove    => RemoveAccount::class
		};
	}
}
