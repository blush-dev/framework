<?php

/**
 * New entry.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Editing;

/**
 * An entry to create (D-756): its type (by name), title, and, optionally,
 * a slug (else one from the title), front matter to `set`, a `body`, a
 * `status` (its workflow's `initial` unless asked otherwise, D-756, D-851), a `date`
 * for scheduling, a `parent` for a tree's page (by key), and a
 * `language` (by code; the site's own when `null`).
 */
final readonly class NewEntry
{
	/**
	 * @param array<string, mixed> $set
	 */
	public function __construct(
		public string $type,
		public string $title,
		public ?string $slug = null,
		public array $set = [],
		public string $body = '',
		public ?string $status = null,
		public ?string $date = null,
		public ?string $parent = null,
		public ?string $language = null
	) {}
}
