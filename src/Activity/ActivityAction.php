<?php

/**
 * Activity action.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Activity;

use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * What a change in the activity log did (D-758), by entry or media file.
 */
enum ActivityAction: string
{
	case EntryCreate  = 'entry.create';
	case EntryUpdate  = 'entry.update';
	case EntryStatus  = 'entry.status';
	case EntryTrash   = 'entry.trash';
	case EntryRestore = 'entry.restore';
	case MediaUpload  = 'media.upload';
	case MediaUpdate  = 'media.update';

	/**
	 * Whether it changed an entry, rather than a media file.
	 */
	public function isEntry(): bool
	{
		return str_starts_with($this->value, 'entry.');
	}

	/**
	 * Whether what it replaced is kept, so it can be undone: creating and
	 * uploading are undone by trashing or deleting instead.
	 */
	public function isUndoable(): bool
	{
		return ! in_array($this, [self::EntryCreate, self::MediaUpload], true);
	}

	/**
	 * Returns how the Activity tab's filter names it.
	 */
	public function label(Messages $messages): Text
	{
		return match ($this) {
			self::EntryCreate  => $messages->t('activity.activity_action.entry_create', 'Created'),
			self::EntryUpdate  => $messages->t('activity.activity_action.entry_update', 'Edited'),
			self::EntryStatus  => $messages->t('activity.activity_action.entry_status', 'Status changed'),
			self::EntryTrash   => $messages->t('activity.activity_action.entry_trash', 'Moved to Trash'),
			self::EntryRestore => $messages->t('activity.activity_action.entry_restore', 'Restored'),
			self::MediaUpload  => $messages->t('activity.activity_action.media_upload', 'Uploaded'),
			self::MediaUpdate  => $messages->t('activity.activity_action.media_update', 'Media edited')
		};
	}
}
