<?php

/**
 * Activity filter.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Activity;

/**
 * What the Activity screen lists (D-758): changes by an account, made a way
 * (and by an app, by its name), of a kind, or to one entry or file. Each
 * left `null` doesn't narrow.
 */
final readonly class ActivityFilter
{
	public function __construct(
		public ?string $account = null,
		public ?Via $via = null,
		public ?string $client = null,
		public ?ActivityAction $action = null,
		public ?string $subject = null
	) {}
}
