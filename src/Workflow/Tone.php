<?php

/**
 * Status tone.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Workflow;

/**
 * How the admin draws a status's pill (D-850): one of its pill kinds,
 * so admin themes keep the colors. `Neutral` is the default.
 */
enum Tone: string
{
	case Neutral = 'neutral';
	case Good    = 'good';
	case Warn    = 'warn';
	case Danger  = 'danger';
	case Accent  = 'accent';
}
