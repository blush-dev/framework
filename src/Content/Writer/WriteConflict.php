<?php

/**
 * Content write conflict.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Writer;

use RuntimeException;
use Blush\Core\BlushException;

/**
 * The file changed since it was read (another person's save, a `git
 * pull`, an editor on disk), so writing would lose that change. Read it
 * again and reapply the edit.
 */
final class WriteConflict extends RuntimeException implements BlushException
{
}
