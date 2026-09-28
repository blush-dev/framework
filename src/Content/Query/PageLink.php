<?php

/**
 * Page link.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Query;

/**
 * One item of a listing's numbered pagination, from
 * `Paginator::links()`. The theme decides the markup and the labels; a
 * link has a URL only when it goes somewhere (not the current page or
 * the dots).
 */
final readonly class PageLink
{
	/**
	 * @param ?int    $number The page it stands for (`null` for the dots).
	 * @param ?string $url    The page's URL path, if it's a link.
	 */
	public function __construct(
		public PageLinkKind $kind,
		public ?int $number = null,
		public ?string $url = null
	) {}

	/**
	 * Returns whether this is the page being viewed.
	 */
	public function isCurrent(): bool
	{
		return $this->kind === PageLinkKind::Current;
	}

	/**
	 * Returns the page number padded with leading zeros to a width, as
	 * in "Page 02": `$link->padded(2)`. The dots return `''`.
	 */
	public function padded(int $width = 2): string
	{
		return $this->number === null ? '' : str_pad((string) $this->number, $width, '0', STR_PAD_LEFT);
	}
}
