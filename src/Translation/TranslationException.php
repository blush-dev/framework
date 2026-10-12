<?php

/**
 * Translation exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Translation;

use InvalidArgumentException;
use Blush\Core\BlushException;

/**
 * A message asked for in a way it can't be: a parameter not named.
 */
final class TranslationException extends InvalidArgumentException implements BlushException
{
}
