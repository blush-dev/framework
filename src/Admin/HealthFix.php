<?php

/**
 * Health fix.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin;

/**
 * Site Health's fixes that change many files, run as a job (D-624), by
 * their endpoint's name (`POST health/{fix}`). Each answers what it
 * changed under its own key (`changed()`) and what `failed`, with why.
 */
enum HealthFix: string
{
	case Ids        = 'ids';
	case MediaIds   = 'media-ids';
	case MediaSizes = 'media-sizes';
	case FileNames  = 'filenames';
	case Folders    = 'folders';
	case Terms      = 'terms';
	case Parents    = 'parents';
	case Refs       = 'refs';

	/**
	 * Returns the key the fix's changes are answered under.
	 */
	public function changed(): string
	{
		return match ($this) {
			self::Ids, self::MediaIds         => 'assigned',
			self::MediaSizes                  => 'recorded',
			self::FileNames, self::Folders    => 'renamed',
			self::Terms, self::Parents        => 'created',
			self::Refs                        => 'filed'
		};
	}

	/**
	 * Returns what the fix changes, for its messages: one and many.
	 *
	 * @return array{string, string}
	 */
	public function noun(): array
	{
		return match ($this) {
			self::MediaIds   => ['media file', 'media files'],
			self::MediaSizes => ['image', 'images'],
			self::FileNames,
			self::Folders    => ['entry', 'entries'],
			self::Terms      => ['term or profile', 'terms and profiles'],
			self::Parents    => ['parent page', 'parent pages'],
			default          => ['file', 'files']
		};
	}
}
