<?php

/**
 * Built-in capabilities.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Auth;

use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * The framework's site capabilities (D-217), seeded into `Capabilities`,
 * where extensions add their own. Managing accounts and roles is seven
 * of them (D-362); each `accounts.*` beyond `view` also needs `view`. What a role may do to entries is per
 * content type (`ContentAction`, D-359).
 *
 * Media (D-407): editing a file's details and deleting it are by whose
 * file it is (its uploader's, or `.others` for anyone else's and for
 * files with no uploader), and need the same action on their own to use
 * `.others`, as content's do. Uploading is by kind
 * (`media.{kind}.upload`, `MediaKind::uploadCapability()`), with
 * `media.*.upload` for every kind.
 *
 * Logs (D-541): reading the site's log on the Tools screen, which can hold
 * paths and error details. Health (D-543): Site Health, its checks and
 * requirements, and its fixes for content and media files. Jobs (D-621):
 * every background job and scheduled task on the Tools screen, with
 * Retry, Delete, and Run Now; anyone can follow the jobs they start.
 * Redirects (D-686): the Redirects screen, adding, changing, and deleting
 * the `redirects` table's rows. Renaming or moving an entry adds one
 * without it, since that's the entry's edit.
 *
 * Secrets (D-715): storing, replacing, and removing the credentials Blush
 * uses to reach other services. Only the owner has it by default; the
 * administrator's list leaves it out (D-500).
 *
 * API tokens (D-749): making tokens for your own account, on Your
 * Account. The owner and the administrator have it by default. Seeing
 * and revoking another account's tokens, and making them for a
 * token-only account, are `accounts.edit`'s. Anyone may see and revoke
 * their own.
 *
 * HTML (D-495): adding raw HTML to a body in the admin, from the allowed
 * list (`html.allowed`) or anything but what's always refused
 * (`html.unfiltered`; `Markdown\Html\HtmlRules`). Without either, a save
 * that adds HTML is refused. Either is enough on its own.
 */
enum Capability: string
{
	case MediaEdit         = 'media.edit';
	case MediaEditOthers   = 'media.edit.others';
	case MediaDelete       = 'media.delete';
	case MediaDeleteOthers = 'media.delete.others';
	case HtmlAllowed       = 'html.allowed';
	case HtmlUnfiltered    = 'html.unfiltered';
	case MenusEdit       = 'menus.edit';
	case SitePublish     = 'site.publish';
	case CacheClear      = 'cache.clear';
	case SiteSettings    = 'site.settings';
	case SiteLogs        = 'site.logs';
	case SiteHealth      = 'site.health';
	case SiteJobs        = 'site.jobs';
	case SiteRedirects   = 'site.redirects';
	case SiteSecrets     = 'site.secrets';
	case SiteActivity    = 'site.activity';
	case AccountsView    = 'accounts.view';
	case AccountsCreate  = 'accounts.create';
	case AccountsEdit    = 'accounts.edit';
	case AccountsRoles   = 'accounts.roles';
	case AccountsSuspend = 'accounts.suspend';
	case AccountsDelete  = 'accounts.delete';
	case RolesManage     = 'roles.manage';
	case ApiTokens       = 'api.tokens';
	case ApiConnect      = 'api.connect';

	/**
	 * Capabilities that are gone (D-407): a saved role that names one has
	 * it dropped when it's read, and loses it on its next save. Nothing
	 * takes its place.
	 *
	 * @var list<string>
	 */
	public const array RETIRED = ['media.upload'];

	/**
	 * The `.others` form of a media capability, or `null` for one that has
	 * none.
	 */
	public function others(): ?self
	{
		return match ($this) {
			self::MediaEdit   => self::MediaEditOthers,
			self::MediaDelete => self::MediaDeleteOthers,
			default           => null
		};
	}

	/**
	 * The capabilities for managing accounts and roles (D-362). Someone
	 * who isn't suspended must always hold all of them.
	 *
	 * @return list<self>
	 */
	public static function users(): array
	{
		return [self::AccountsView, self::AccountsCreate, self::AccountsEdit, self::AccountsRoles, self::AccountsSuspend, self::AccountsDelete, self::RolesManage];
	}

	/**
	 * Returns the group the admin shows the capability in.
	 */
	public function group(Messages $messages): Text
	{
		return match ($this) {
			self::MediaEdit, self::MediaEditOthers,
			self::MediaDelete, self::MediaDeleteOthers              => $messages->t('auth.capability_group.media', 'Media'),
			self::HtmlAllowed, self::HtmlUnfiltered                 => $messages->t('auth.capability_group.html', 'HTML'),
			self::MenusEdit                                         => $messages->t('auth.capability_group.structure', 'Structure'),
			self::SitePublish, self::CacheClear,
			self::SiteSettings, self::SiteLogs, self::SiteHealth,
			self::SiteJobs, self::SiteRedirects, self::SiteSecrets,
			self::SiteActivity                                      => $messages->t('auth.capability_group.site', 'Site'),
			default                                                 => $messages->t('auth.capability_group.users', 'Users')
		};
	}

	/**
	 * Returns the capability's label.
	 */
	public function label(Messages $messages): Text
	{
		return match ($this) {
			self::MediaEdit         => $messages->t('auth.capability.media_edit', 'Edit their own files'),
			self::MediaEditOthers   => $messages->t('auth.capability.media_edit_others', 'Edit anyone\'s files'),
			self::MediaDelete       => $messages->t('auth.capability.media_delete', 'Delete their own files'),
			self::MediaDeleteOthers => $messages->t('auth.capability.media_delete_others', 'Delete anyone\'s files'),
			self::HtmlAllowed       => $messages->t('auth.capability.html_allowed', 'Add HTML from the allowed list'),
			self::HtmlUnfiltered    => $messages->t('auth.capability.html_unfiltered', 'Add any HTML but what\'s always refused'),
			self::MenusEdit         => $messages->t('auth.capability.menus_edit', 'Edit menus'),
			self::SitePublish       => $messages->t('auth.capability.site_publish', 'Publish the site'),
			self::CacheClear        => $messages->t('auth.capability.cache_clear', 'Clear caches'),
			self::SiteSettings      => $messages->t('auth.capability.site_settings', 'Change site settings'),
			self::SiteLogs          => $messages->t('auth.capability.site_logs', 'Read the site\'s log'),
			self::SiteHealth        => $messages->t('auth.capability.site_health', 'See site health and fix what it finds'),
			self::SiteJobs          => $messages->t('auth.capability.site_jobs', 'See and manage background jobs'),
			self::SiteRedirects     => $messages->t('auth.capability.site_redirects', 'Manage redirects'),
			self::SiteSecrets       => $messages->t('auth.capability.site_secrets', 'Manage secrets'),
			self::SiteActivity      => $messages->t('auth.capability.site_activity', 'See activity and undo changes'),
			self::AccountsView      => $messages->t('auth.capability.accounts_view', 'See accounts and roles'),
			self::AccountsCreate    => $messages->t('auth.capability.accounts_create', 'Create accounts'),
			self::AccountsEdit      => $messages->t('auth.capability.accounts_edit', 'Edit accounts'),
			self::AccountsRoles     => $messages->t('auth.capability.accounts_roles', 'Give and take roles'),
			self::AccountsSuspend   => $messages->t('auth.capability.accounts_suspend', 'Suspend and reinstate accounts'),
			self::AccountsDelete    => $messages->t('auth.capability.accounts_delete', 'Remove accounts'),
			self::RolesManage       => $messages->t('auth.capability.roles_manage', 'Manage roles'),
			self::ApiTokens         => $messages->t('auth.capability.api_tokens', 'Make API tokens for their own account'),
			self::ApiConnect        => $messages->t('auth.capability.api_connect', 'Connect apps, such as Claude, to act as them')
		};
	}
}
