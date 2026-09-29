<?php

/**
 * Content write exception.
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
 * A content write that couldn't be done safely: a path outside the
 * content folder or of a type that isn't content, a file that already
 * exists, a value that isn't data, or an edit whose result wouldn't read
 * back as intended. Nothing is written.
 */
final class WriteException extends RuntimeException implements BlushException
{
}
