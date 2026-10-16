<?php

/**
 * Core part.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Backup;

use Blush\Activity\ActivityLog;
use Blush\Auth\Accounts;
use Blush\Auth\Roles;
use Blush\Auth\Tokens;
use Blush\Content\Record\EntryTable;
use Blush\Content\Routing\DataRedirects;
use Blush\Content\Type\DefinitionTables;
use Blush\Field\FieldLibraryLoader;
use Blush\Job\RecordJobStore;
use Blush\Mcp\OAuth\OAuthClients;
use Blush\Mcp\OAuth\OAuthGrants;
use Blush\Media\MediaMetadataStore;
use Blush\Menu\MenuLoader;
use Blush\Settings\SettingGroups;
use Blush\Storage\Record\Ref;
use Blush\Translation\Messages;
use Blush\Workflow\WorkflowTables;

/**
 * Core's backup parts (D-883), in the order they're shown. Sessions and
 * secrets are in none, so they're never backed up: restoring sessions
 * would bring back sign-ins that ended, and secrets are ciphertext only
 * the site's key reads (`BackupParts::LEFT_OUT`).
 */
enum CorePart: string
{
	case Content       = 'content';
	case Types         = 'types';
	case Fields        = 'fields';
	case Relationships = 'relationships';
	case Media         = 'media';
	case Settings      = 'settings';
	case Redirects     = 'redirects';
	case Menus         = 'menus';
	case Workflows     = 'workflows';
	case Jobs          = 'jobs';
	case Activity      = 'activity';
	case Accounts      = 'accounts';
	case Roles         = 'roles';
	case Access        = 'access';

	/**
	 * Returns the part's group.
	 */
	public function group(): BackupGroup
	{
		return match ($this) {
			self::Content, self::Types, self::Fields, self::Relationships, self::Media              => BackupGroup::Content,
			self::Settings, self::Redirects, self::Menus, self::Workflows, self::Jobs, self::Activity => BackupGroup::Site,
			self::Accounts, self::Roles, self::Access                                               => BackupGroup::Users
		};
	}

	/**
	 * Returns the tables the part holds.
	 *
	 * @return list<string>
	 */
	public function tables(): array
	{
		return match ($this) {
			self::Content       => [EntryTable::TABLE, Ref::TABLE],
			self::Types         => [DefinitionTables::TYPES],
			self::Fields        => [FieldLibraryLoader::TABLE],
			self::Relationships => [DefinitionTables::RELATIONS],
			self::Media         => [MediaMetadataStore::TABLE],
			self::Settings      => [SettingGroups::TABLE],
			self::Redirects     => [DataRedirects::TABLE],
			self::Menus         => [MenuLoader::TABLE],
			self::Workflows     => [WorkflowTables::STATUSES, WorkflowTables::WORKFLOWS],
			self::Jobs          => [RecordJobStore::TABLE, RecordJobStore::STATE],
			self::Activity      => [ActivityLog::TABLE],
			self::Accounts      => [Accounts::TABLE],
			self::Roles         => [Roles::TABLE],
			self::Access        => [Tokens::TABLE, OAuthClients::TABLE, OAuthGrants::GRANTS, OAuthGrants::SECRETS]
		};
	}

	/**
	 * Returns the part.
	 */
	public function part(Messages $messages): BackupPart
	{
		[$label, $description] = match ($this) {
			self::Content       => [$messages->t('backup.part.content', 'Content'), $messages->t('backup.part.content_description', 'Entries and how they\'re linked')],
			self::Types         => [$messages->t('backup.part.types', 'Types'), $messages->t('backup.part.types_description', 'Content types the site defines')],
			self::Fields        => [$messages->t('backup.part.fields', 'Fields'), $messages->t('backup.part.fields_description', 'The field library')],
			self::Relationships => [$messages->t('backup.part.relationships', 'Relationships'), $messages->t('backup.part.relationships_description', 'How types and entries connect')],
			self::Media         => [$messages->t('backup.part.media', 'Media Details'), $messages->t('backup.part.media_description', 'Titles, alt text, and captions, not the files')],
			self::Settings      => [$messages->t('backup.part.settings', 'Settings'), $messages->t('backup.part.settings_description', 'Site, plugin, and theme settings')],
			self::Redirects     => [$messages->t('backup.part.redirects', 'Redirects'), $messages->t('backup.part.redirects_description', 'Stored redirects')],
			self::Menus         => [$messages->t('backup.part.menus', 'Menus'), $messages->t('backup.part.menus_description', 'Menus and their items')],
			self::Workflows     => [$messages->t('backup.part.workflows', 'Workflows'), $messages->t('backup.part.workflows_description', 'Statuses and workflows')],
			self::Jobs          => [$messages->t('backup.part.jobs', 'Jobs'), $messages->t('backup.part.jobs_description', 'Finished jobs and the schedule\'s state')],
			self::Activity      => [$messages->t('backup.part.activity', 'Activity'), $messages->t('backup.part.activity_description', 'Changes made by apps and the API, for Undo')],
			self::Accounts      => [$messages->t('backup.part.accounts', 'Accounts'), $messages->t('backup.part.accounts_description', 'Accounts that can sign in, with their password hashes')],
			self::Roles         => [$messages->t('backup.part.roles', 'Roles'), $messages->t('backup.part.roles_description', 'Roles and the capabilities they give')],
			self::Access        => [$messages->t('backup.part.access', 'API Access'), $messages->t('backup.part.access_description', 'API tokens and connected apps')]
		};

		return new BackupPart($this->value, $this->group(), $label, $description, $this->tables());
	}
}
