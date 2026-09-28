<?php

/**
 * Unresolved menu link.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Menu\Link;

use RuntimeException;
use Blush\Core\BlushException;

/**
 * A menu item's link that doesn't lead anywhere right now: a missing,
 * unpublished, or hidden entry, or an unknown route. The item is left out
 * of the menu, and the message says why.
 */
final class UnresolvedLink extends RuntimeException implements BlushException
{
}
