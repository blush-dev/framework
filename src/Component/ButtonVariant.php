<?php

/**
 * Button variant.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Component;

/**
 * A button's style (D-189). A fixed pair for now; a variant system any
 * component can register is planned (see `open-questions.md`).
 */
enum ButtonVariant: string
{
	case Primary   = 'primary';
	case Secondary = 'secondary';
}
