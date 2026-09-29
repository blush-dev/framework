<?php

/**
 * Built-in admin actions.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin\Action;

/**
 * The framework's admin actions, keyed by name (D-019, D-222).
 */
enum AdminActionType: string
{
	case Publish     = 'publish';
	case Reindex     = 'reindex';
	case ClearCaches = 'clear-caches';

	/**
	 * Returns the action's class.
	 *
	 * @return class-string<AdminAction>
	 */
	public function className(): string
	{
		return match ($this) {
			self::Publish     => PublishAction::class,
			self::Reindex     => ReindexAction::class,
			self::ClearCaches => ClearCachesAction::class
		};
	}
}
