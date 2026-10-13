<?php

/**
 * Inline block node.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Markdown\CommonMark\Block;

use League\CommonMark\Node\Inline\AbstractInline;

/**
 * A `:name[text]{attrs}` span inside a paragraph.
 */
final class InlineBlock extends AbstractInline
{
	/**
	 * @param array<string, string> $attributes
	 */
	public function __construct(
		public readonly string $name,
		public readonly string $label,
		public readonly array $attributes
	) {
		parent::__construct();
	}
}
