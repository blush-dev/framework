<?php

/**
 * Entry edit.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Editing;

/**
 * A change to an entry (D-756), as the admin's editor, the MCP server's
 * tools, and later the API ask for it: front matter to `set` and
 * `remove`, a new `body`, a `status` (`draft`, `published`, or
 * `scheduled`, with a future `date`), a new `slug`, a new
 * `parent` for a tree's page (by key, `''` for the top; `null` stays
 * put), another `language` (by code), and whether a published entry
 * that moves or is renamed leaves a `redirect` from its old address.
 */
final readonly class EntryEdit
{
	/**
	 * @param array<string, mixed> $set
	 * @param list<string>         $remove
	 */
	public function __construct(
		public array $set = [],
		public array $remove = [],
		public ?string $body = null,
		public ?string $status = null,
		public ?string $date = null,
		public ?string $slug = null,
		public ?string $parent = null,
		public ?string $language = null,
		public bool $redirect = false
	) {}

	/**
	 * Whether it changes only the status (with the date scheduling
	 * takes).
	 */
	public function isStatusOnly(): bool
	{
		return $this->status !== null
			&& array_diff(array_keys($this->set), ['status', 'date']) === []
			&& array_diff($this->remove, ['status']) === []
			&& $this->body === null
			&& $this->slug === null
			&& $this->parent === null
			&& $this->language === null;
	}
}
