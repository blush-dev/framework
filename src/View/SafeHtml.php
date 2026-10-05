<?php

/**
 * Safe HTML.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View;

use Stringable;

/**
 * A value that prints as rendered HTML, such as a component waiting for
 * its slots (D-502). PHP templates print it with `<?= ?>`; a view engine
 * that escapes on its own maps it to its own safe type (Twig's
 * `addSafeClass()`, say) so it isn't escaped twice.
 */
interface SafeHtml extends Stringable
{
}
