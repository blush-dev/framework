<?php

/**
 * Trashed entry.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Writer;

use DateTimeImmutable;

/**
 * An entry in the trash (D-237): where it came from and what it held,
 * read from its file, so the admin can list it and restore it.
 */
final readonly class TrashedEntry
{
	/**
	 * @param string              $name        The trash's name for it, for `restore()` and `purge()`.
	 * @param string              $path        The path it had, and has again once restored.
	 * @param bool                $bundle      Whether its bundle's folder (with media) went with it.
	 * @param DateTimeImmutable   $trashed     When it was trashed.
	 * @param array<mixed>        $frontMatter Its front matter, as parsed; empty when it can't be read.
	 * @param ?string             $id          Its id (D-481), or `null` without a valid one.
	 */
	public function __construct(
		public string $name,
		public string $path,
		public bool $bundle,
		public DateTimeImmutable $trashed,
		public array $frontMatter,
		public ?string $id = null
	) {}

	/**
	 * The entry's title, or `''`.
	 */
	public function title(): string
	{
		$title = $this->frontMatter['title'] ?? '';

		return is_string($title) ? $title : '';
	}
}
