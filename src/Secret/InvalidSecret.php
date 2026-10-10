<?php

/**
 * Invalid secret.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Secret;

/**
 * A secret asked for in a way that can't work: a name that isn't one, a
 * secret nobody declared, an empty value, or storing one on a site
 * without a key (`SECRETS_KEY`).
 */
final class InvalidSecret extends SecretException
{
}
