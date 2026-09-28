<?php

/**
 * Directive node renderer.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Markdown\CommonMark\Directive;

use InvalidArgumentException;
use Override;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use Blush\Markdown\Directive;
use Blush\Markdown\DirectiveKind;
use Blush\Markdown\DirectiveRenderer;
use Blush\Markdown\MarkdownContext;

/**
 * Renders directive nodes through the `DirectiveRenderer`. An unknown
 * directive (or one with no renderer) renders as plain content: a
 * container's blocks, a leaf's label as a paragraph, or an inline
 * directive's text (D-026). Each directive carries the base folder of
 * the Markdown being converted, from the parser's context.
 */
final readonly class DirectiveNodeRenderer implements NodeRendererInterface
{
	public function __construct(
		private ?DirectiveRenderer $renderer = null,
		private ?MarkdownContext $context = null
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
	{
		[$directive, $fallback] = match (true) {
			$node instanceof ContainerDirective => $this->container($node, $childRenderer),
			$node instanceof LeafDirective      => $this->leaf($node),
			$node instanceof InlineDirective    => $this->inline($node),
			default                             => throw new InvalidArgumentException('Not a directive node: ' . $node::class)
		};

		return $this->renderer?->render($directive) ?? $fallback;
	}

	/**
	 * @return array{Directive, string}
	 */
	private function container(ContainerDirective $node, ChildNodeRendererInterface $childRenderer): array
	{
		$content = $childRenderer->renderNodes($node->children());

		return [new Directive($node->name, DirectiveKind::Container, $node->attributes, $node->label, $content, $this->base(), self::outline($node)), $content];
	}

	/**
	 * @return array{Directive, string}
	 */
	private function leaf(LeafDirective $node): array
	{
		$content = self::escape($node->label);

		return [
			new Directive($node->name, DirectiveKind::Leaf, $node->attributes, $node->label, $content, $this->base(), self::outline($node)),
			$content === '' ? '' : "<p>{$content}</p>"
		];
	}

	/**
	 * @return array{Directive, string}
	 */
	private function inline(InlineDirective $node): array
	{
		$content = self::escape($node->label);

		return [new Directive($node->name, DirectiveKind::Inline, $node->attributes, $node->label, $content, $this->base()), $content];
	}

	/**
	 * Returns the outline `CollectOutline` gave a node, if any.
	 *
	 * @return list<array{level: int, text: string, id: string}>
	 */
	private static function outline(Node $node): array
	{
		/** @var list<array{level: int, text: string, id: string}> Set by `CollectOutline`. */
		return $node->data->get('blush/outline', []);
	}

	/**
	 * Returns the base folder of the Markdown being converted.
	 */
	private function base(): string
	{
		return $this->context === null ? '' : $this->context->base;
	}

	/**
	 * Escapes text for HTML.
	 */
	private static function escape(string $text): string
	{
		return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
	}
}
