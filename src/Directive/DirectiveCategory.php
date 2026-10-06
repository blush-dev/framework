<?php

/**
 * Directive categories.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Directive;

/**
 * The groups the admin's inserter shows the core directives in (D-243).
 * A theme's, the site's, or an extension's directives are grouped by
 * where they come from instead.
 */
enum DirectiveCategory: string
{
	case Text       = 'text';
	case Media      = 'media';
	case Layout     = 'layout';
	case Navigation = 'navigation';
	case Data       = 'data';
}
