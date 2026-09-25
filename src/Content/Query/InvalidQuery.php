<?php

/**
 * Invalid query exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Query;

use InvalidArgumentException;
use Blush\Content\ContentException;

/**
 * Thrown for query arguments that don't make sense: an unknown key, a
 * value of the wrong type, or a query run without a repository.
 */
final class InvalidQuery extends InvalidArgumentException implements ContentException
{
}
