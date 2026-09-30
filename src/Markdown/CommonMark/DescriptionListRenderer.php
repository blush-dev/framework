<?php

/**
 * Description list renderer.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Markdown\CommonMark;

use InvalidArgumentException;
use Override;
use Stringable;
use League\CommonMark\Extension\DescriptionList\Node\Description;
use League\CommonMark\Extension\DescriptionList\Node\DescriptionList;
use League\CommonMark\Extension\DescriptionList\Node\DescriptionTerm;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

/**
 * Renders a description list's `<dl>`, `<dt>`, and `<dd>` as
 * league/commonmark's own renderers do, but with the attributes an
 * attribute block gave them (D-282), which those leave out: `{.terms}` on
 * the line above or below a list, `{#first}` at the end of a term, and
 * `{.note}` at the end of a definition (`DescriptionAttributes`).
 */
final readonly class DescriptionListRenderer implements NodeRendererInterface
{
	/**
	 * @inheritDoc
	 */
	#[Override]
	public function render(Node $node, ChildNodeRendererInterface $childRenderer): Stringable
	{
		$tag = match (true) {
			$node instanceof DescriptionList => 'dl',
			$node instanceof DescriptionTerm => 'dt',
			$node instanceof Description     => 'dd',
			default                          => throw new InvalidArgumentException(sprintf('%s renders description lists; %s given.', self::class, $node::class))
		};

		$attributes = $node->data->get('attributes', []);
		$attributes = is_array($attributes) ? $attributes : [];
		$separator  = $tag === 'dl' ? $childRenderer->getBlockSeparator() : '';

		/** @var array<string, string|string[]|bool> $attributes */
		return new HtmlElement($tag, $attributes, $separator . $childRenderer->renderNodes($node->children()) . $separator);
	}
}
