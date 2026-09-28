<?php

/**
 * Table of contents item.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Component;

/**
 * A heading in a table of contents (D-183): its text, its ID (the link's
 * `#` target), and the headings nested under it.
 */
final readonly class TocItem
{
	/**
	 * @param list<TocItem> $children
	 */
	public function __construct(
		public string $text,
		public string $id,
		public array $children = []
	) {}

	/**
	 * Returns a copy with more items nested under it.
	 *
	 * @param list<TocItem> $children
	 */
	public function withChildren(array $children): self
	{
		return new self($this->text, $this->id, [...$this->children, ...$children]);
	}

	/**
	 * Returns the URL fragment that links to the heading.
	 */
	public function href(): string
	{
		return "#{$this->id}";
	}
}
