<?php

/**
 * Key type.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Console;

/**
 * What a key pressed in a prompt is (D-719).
 */
enum KeyType
{
	case Character;
	case Enter;
	case Space;
	case Backspace;
	case Delete;
	case Tab;
	case Up;
	case Down;
	case Left;
	case Right;
	case Home;
	case End;
	case Escape;
	case Interrupt;
	case Other;
}
