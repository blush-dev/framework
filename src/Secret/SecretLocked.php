<?php

/**
 * Locked secret.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Secret;

/**
 * A secret the environment sets, so it can't be stored over: the
 * operator's value always wins.
 */
final class SecretLocked extends SecretException
{
}
