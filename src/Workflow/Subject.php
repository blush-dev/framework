<?php

/**
 * Status subject.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Workflow;

/**
 * What a status is for (D-735): entries, or responses (comments,
 * reviews, webmentions) once they're built. Each subject has its own
 * statuses, so `approved` and `spam` never show for entries.
 */
enum Subject: string
{
	case Entries   = 'entries';
	case Responses = 'responses';
}
