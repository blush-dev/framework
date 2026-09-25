<?php

/**
 * Body format.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Parser;

/**
 * What a document's body is written in, which decides how it's rendered.
 */
enum BodyFormat: string
{
	case Markdown = 'markdown';
	case Html     = 'html';
}
