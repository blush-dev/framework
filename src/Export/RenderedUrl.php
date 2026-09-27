<?php

/**
 * Rendered URL.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Export;

/**
 * One URL the crawler asked the kernel for, and its answer. `$referrer`
 * is the page that linked to it, or `null` when a source listed it.
 */
final readonly class RenderedUrl
{
	public function __construct(
		public string $path,
		public int $status,
		public string $contentType = '',
		public string $body = '',
		public ?string $location = null,
		public ?string $referrer = null
	) {}

	/**
	 * Returns whether the answer is a page to export.
	 */
	public function isOk(): bool
	{
		return $this->status === 200;
	}

	/**
	 * Returns whether the answer is a redirect.
	 */
	public function isRedirect(): bool
	{
		return $this->status >= 300 && $this->status < 400 && $this->location !== null;
	}

	/**
	 * Returns whether the answer is an HTML page.
	 */
	public function isHtml(): bool
	{
		return (explode(';', $this->contentType)[0]
			|> trim(...)
			|> strtolower(...)) === 'text/html';
	}
}
