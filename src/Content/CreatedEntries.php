<?php

/**
 * Created entries.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content;

/**
 * What a tool that writes missing entries did (missing terms, D-584;
 * missing parent pages, D-656): each entry it wrote, where its store
 * keeps it, and why each it couldn't write was left, both by
 * `{type}/{key}`.
 */
final readonly class CreatedEntries
{
	/**
	 * @param array<string, string> $created Paths by `{type}/{key}`.
	 * @param array<string, string> $failed  Messages by `{type}/{key}`.
	 */
	public function __construct(
		public array $created = [],
		public array $failed = []
	) {}
}
