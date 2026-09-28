<?php

/**
 * Callout tone.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Component;

/**
 * A callout's tone, which themes style (`component-callout--warning`).
 */
enum CalloutTone: string
{
	case Note    = 'note';
	case Info    = 'info';
	case Tip     = 'tip';
	case Warning = 'warning';
	case Danger  = 'danger';
}
