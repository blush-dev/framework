<?php

/**
 * Edit refusal.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Editing;

use Blush\Http\Status;

/**
 * Why an edit was refused (D-756), so each way in answers it its own
 * way: the admin's API and a later API by HTTP status, the MCP server as
 * a tool's error.
 */
enum Refusal
{
	/**
	 * The edit doesn't make sense (an unknown status, a field that isn't
	 * a map).
	 */
	case Invalid;

	/**
	 * The account may not make it.
	 */
	case Forbidden;

	/**
	 * There's nothing there to edit.
	 */
	case NotFound;

	/**
	 * It was asked of a revision that's no longer the entry's.
	 */
	case Conflict;

	/**
	 * It can't be made as asked: a slug that's taken, a relation out of
	 * its limits, an entry in the trash.
	 */
	case Unprocessable;

	/**
	 * It needs the revision that was read, so no one's change is lost.
	 */
	case RevisionRequired;

	/**
	 * It's larger than the site allows (an upload).
	 */
	case TooLarge;

	/**
	 * Something on the server failed: a folder that can't be written.
	 */
	case Failed;

	/**
	 * The HTTP status that answers it.
	 */
	public function status(): Status
	{
		return match ($this) {
			self::Invalid          => Status::BadRequest,
			self::Forbidden        => Status::Forbidden,
			self::NotFound         => Status::NotFound,
			self::Conflict         => Status::Conflict,
			self::Unprocessable    => Status::UnprocessableContent,
			self::RevisionRequired => Status::PreconditionRequired,
			self::TooLarge         => Status::ContentTooLarge,
			self::Failed           => Status::InternalServerError
		};
	}
}
