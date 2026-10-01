<?php

/**
 * Bracketed span renderer.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Markdown\CommonMark;

use Override;
use Stringable;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

/**
 * Renders a `BracketedSpan` as a `<span>` with its attributes (D-305).
 */
final class BracketedSpanRenderer implements NodeRendererInterface
{
	/**
	 * @inheritDoc
	 */
	#[Override]
	public function render(Node $node, ChildNodeRendererInterface $childRenderer): Stringable
	{
		BracketedSpan::assertInstanceOf($node);

		/** @var array<string, string|string[]|bool> $attributes */
		$attributes = $node->data->get('attributes', []);

		return new HtmlElement('span', $attributes, $childRenderer->renderNodes($node->children()));
	}
}
