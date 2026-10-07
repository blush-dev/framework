<?php

/**
 * Created terms.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content;

/**
 * What `MissingTerms::create()` did: each file it wrote, and why each
 * term it couldn't write was left, both by `{type}/{slug}`.
 */
final readonly class CreatedTerms
{
	/**
	 * @param array<string, string> $created Paths by `{type}/{slug}`.
	 * @param array<string, string> $failed  Messages by `{type}/{slug}`.
	 */
	public function __construct(
		public array $created = [],
		public array $failed = []
	) {}
}
