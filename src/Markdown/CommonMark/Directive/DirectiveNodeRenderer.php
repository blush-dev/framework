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

/**
 * Renders directive nodes through the `DirectiveRenderer`. An unknown
 * directive (or one with no renderer) renders as plain content: a
 * container's blocks, a leaf's label as a paragraph, or an inline
 * directive's text (D-026).
 */
final readonly class DirectiveNodeRenderer implements NodeRendererInterface
{
	public function __construct(private ?DirectiveRenderer $renderer = null)
	{}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
	{
		[$directive, $fallback] = match (true) {
			$node instanceof ContainerDirective => self::container($node, $childRenderer),
			$node instanceof LeafDirective      => self::leaf($node),
			$node instanceof InlineDirective    => self::inline($node),
			default                             => throw new InvalidArgumentException('Not a directive node: ' . $node::class)
		};

		return $this->renderer?->render($directive) ?? $fallback;
	}

	/**
	 * @return array{Directive, string}
	 */
	private static function container(ContainerDirective $node, ChildNodeRendererInterface $childRenderer): array
	{
		$content = $childRenderer->renderNodes($node->children());

		return [new Directive($node->name, DirectiveKind::Container, $node->attributes, $node->label, $content), $content];
	}

	/**
	 * @return array{Directive, string}
	 */
	private static function leaf(LeafDirective $node): array
	{
		$content = self::escape($node->label);

		return [
			new Directive($node->name, DirectiveKind::Leaf, $node->attributes, $node->label, $content),
			$content === '' ? '' : "<p>{$content}</p>"
		];
	}

	/**
	 * @return array{Directive, string}
	 */
	private static function inline(InlineDirective $node): array
	{
		$content = self::escape($node->label);

		return [new Directive($node->name, DirectiveKind::Inline, $node->attributes, $node->label, $content), $content];
	}

	/**
	 * Escapes text for HTML.
	 */
	private static function escape(string $text): string
	{
		return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
	}
}
