<?php

/**
 * Tool type.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tool;

use Blush\Tool\Tools\ClearCaches;
use Blush\Tool\Tools\CreateEntry;
use Blush\Tool\Tools\EditBody;
use Blush\Tool\Tools\GetEntry;
use Blush\Tool\Tools\GetMedia;
use Blush\Tool\Tools\GetType;
use Blush\Tool\Tools\ListTypes;
use Blush\Tool\Tools\Publish;
use Blush\Tool\Tools\Reindex;
use Blush\Tool\Tools\RestoreEntry;
use Blush\Tool\Tools\SearchEntries;
use Blush\Tool\Tools\SearchMedia;
use Blush\Tool\Tools\SetStatus;
use Blush\Tool\Tools\TrashEntry;
use Blush\Tool\Tools\UpdateEntry;
use Blush\Tool\Tools\UpdateMedia;
use Blush\Tool\Tools\UploadMedia;

/**
 * Blush's own tools (D-755, D-756, D-761), by key.
 */
enum ToolType: string
{
	case ListTypes     = 'core/list-types';
	case GetType       = 'core/get-type';
	case SearchEntries = 'core/search-entries';
	case GetEntry      = 'core/get-entry';
	case SearchMedia   = 'core/search-media';
	case GetMedia      = 'core/get-media';
	case CreateEntry   = 'core/create-entry';
	case UpdateEntry   = 'core/update-entry';
	case EditBody      = 'core/edit-body';
	case SetStatus     = 'core/set-status';
	case TrashEntry    = 'core/trash-entry';
	case RestoreEntry  = 'core/restore-entry';
	case UpdateMedia   = 'core/update-media';
	case UploadMedia   = 'core/upload-media';
	case Publish       = 'core/publish';
	case Reindex       = 'core/reindex';
	case ClearCaches   = 'core/clear-caches';

	/**
	 * Returns the tool's class.
	 *
	 * @return class-string<Tool>
	 */
	public function className(): string
	{
		return match ($this) {
			self::ListTypes     => ListTypes::class,
			self::GetType       => GetType::class,
			self::SearchEntries => SearchEntries::class,
			self::GetEntry      => GetEntry::class,
			self::SearchMedia   => SearchMedia::class,
			self::GetMedia      => GetMedia::class,
			self::CreateEntry   => CreateEntry::class,
			self::UpdateEntry   => UpdateEntry::class,
			self::EditBody      => EditBody::class,
			self::SetStatus     => SetStatus::class,
			self::TrashEntry    => TrashEntry::class,
			self::RestoreEntry  => RestoreEntry::class,
			self::UpdateMedia   => UpdateMedia::class,
			self::UploadMedia   => UploadMedia::class,
			self::Publish       => Publish::class,
			self::Reindex       => Reindex::class,
			self::ClearCaches   => ClearCaches::class
		};
	}

	/**
	 * Whether a tool registered under a key is Blush's own.
	 */
	public static function isBuiltIn(string $key, string $className): bool
	{
		return self::tryFrom($key)?->className() === $className;
	}
}
