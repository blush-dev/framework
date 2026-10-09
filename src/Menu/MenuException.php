<?php

/**
 * Menu exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Menu;

use RuntimeException;
use Blush\Core\BlushException;

/**
 * A menu problem that stops it from loading: a theme's location
 * declaration with the wrong shape, menus or assignments that can't be
 * read, or a change that can't be saved.
 */
final class MenuException extends RuntimeException implements BlushException
{
}
