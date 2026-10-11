<?php

/**
 * MCP tool type.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Mcp;

use Blush\Mcp\Tools\CreateEntry;
use Blush\Mcp\Tools\EditBody;
use Blush\Mcp\Tools\GetEntry;
use Blush\Mcp\Tools\GetMedia;
use Blush\Mcp\Tools\GetType;
use Blush\Mcp\Tools\ListTypes;
use Blush\Mcp\Tools\RestoreEntry;
use Blush\Mcp\Tools\SearchEntries;
use Blush\Mcp\Tools\SearchMedia;
use Blush\Mcp\Tools\SetStatus;
use Blush\Mcp\Tools\TrashEntry;
use Blush\Mcp\Tools\UpdateEntry;
use Blush\Mcp\Tools\UpdateMedia;
use Blush\Mcp\Tools\UploadMedia;

/**
 * Blush's own tools (D-755, D-756), by name.
 */
enum ToolType: string
{
	case ListTypes     = 'list_types';
	case GetType       = 'get_type';
	case SearchEntries = 'search_entries';
	case GetEntry      = 'get_entry';
	case SearchMedia   = 'search_media';
	case GetMedia      = 'get_media';
	case CreateEntry   = 'create_entry';
	case UpdateEntry   = 'update_entry';
	case EditBody      = 'edit_body';
	case SetStatus     = 'set_status';
	case TrashEntry    = 'trash_entry';
	case RestoreEntry  = 'restore_entry';
	case UpdateMedia   = 'update_media';
	case UploadMedia   = 'upload_media';

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
			self::UploadMedia   => UploadMedia::class
		};
	}

	/**
	 * Whether a tool registered under a name is Blush's own.
	 */
	public static function isBuiltIn(string $name, string $className): bool
	{
		return self::tryFrom($name)?->className() === $className;
	}
}
