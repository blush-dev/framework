<?php

/**
 * Container directive node.
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
 * A `:::name[label]{attrs}` block, whose children are the Markdown blocks
 * up to its closing fence.
 */
final class ContainerDirective extends AbstractBlock
{
	/**
	 * @param array<string, string> $attributes
	 */
	public function __construct(
		public readonly string $name,
		public readonly string $label,
		public readonly array $attributes,
		public readonly int $fence
	) {
		parent::__construct();
	}

	/**
	 * Whether its closing fence was found, rather than the end of the
	 * document closing it (D-530).
	 */
	public bool $closed = false;
}
