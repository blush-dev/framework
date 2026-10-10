<?php

/**
 * Missing secret.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Secret;

/**
 * A secret that's needed but isn't set, in the environment or stored.
 */
final class SecretMissing extends SecretException
{
}
