<?php

/**
 * Parsed document.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Parser;

/**
 * A content file split into its front matter and its unrendered body.
 */
final readonly class Document
{
	/**
	 * @param array<array-key, mixed> $frontMatter
	 */
	public function __construct(
		public array $frontMatter = [],
		public string $body = '',
		public BodyFormat $format = BodyFormat::Markdown
	) {}
}
