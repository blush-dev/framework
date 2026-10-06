<?php

/**
 * Leaf directive node.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Markdown\CommonMark\Directive;

use League\CommonMark\Node\Block\AbstractBlock;

/**
 * A `::name[label]{attrs}` line.
 */
final class LeafDirective extends AbstractBlock
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

	/**
	 * Whether it was written as a container (`:::name`) though it isn't
	 * one (D-530), so it renders as an unknown directive.
	 */
	public bool $misplaced = false;
}
