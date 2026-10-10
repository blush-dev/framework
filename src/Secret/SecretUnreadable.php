<?php

/**
 * Unreadable secret.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Secret;

/**
 * A stored secret that won't decrypt: written under a key the site no
 * longer has, changed, or moved from another name. It's reported as
 * unreadable, never as missing.
 */
final class SecretUnreadable extends SecretException
{
}
