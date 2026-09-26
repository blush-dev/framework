<?php

/**
 * Content puller.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Publish;

/**
 * Brings a folder's files up to date from elsewhere before a publish:
 * `git pull` by default (`GitPuller`). A site that deploys content
 * another way (rsync, an object store) binds its own.
 */
interface Puller
{
	/**
	 * Updates the folder and reports what happened. Failures are
	 * reported, not thrown, so the caller can say why.
	 */
	public function pull(string $directory): PullResult;
}
