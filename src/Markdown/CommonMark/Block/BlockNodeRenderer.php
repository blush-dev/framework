<?php

/**
 * Block node renderer.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Markdown\CommonMark\Block;

use InvalidArgumentException;
use Override;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use Blush\Block\ParsedBlock;
use Blush\Block\BlockKind;
use Blush\Block\BlockRenderer;
use Blush\Block\BlockRules;

/**
 * Renders block nodes through the `BlockRenderer`. An unknown
 * block (or one with no renderer) renders as plain content: a
 * container's blocks, a leaf's label as a paragraph, or an inline
 * block's text (D-026), and so does a registered one written in a
 * form it isn't registered for (`BlockRules::forms()`, D-530). A
 * container that holds only some things (`BlockRules`, D-529) renders only those: a gallery's images.
 * Its paragraphs keep only the images (or links around one) and inline
 * blocks it holds, one to a line, so each image is a figure; other
 * blocks it doesn't hold, and every other block, are left out. One
 * never closed (D-530) renders everything, so a missing `:::` doesn't
 * take the rest of the document with it. A leaf or container directly
 * in a container block is told that container's name (D-708).
 */
final readonly class BlockNodeRenderer implements NodeRendererInterface
{
	/**
	 * The document data key holding the language the Markdown is written
	 * in (D-459), which each block is given.
	 */
	public const string LANGUAGE = 'blush_language';

	public function __construct(
		private ?BlockRenderer $renderer = null
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
	{
		[$block, $fallback] = match (true) {
			$node instanceof ContainerBlock => $this->container($node, $childRenderer),
			$node instanceof LeafBlock      => $this->leaf($node),
			$node instanceof InlineBlock    => $this->inline($node),
			default                         => throw new InvalidArgumentException('Not a block node: ' . $node::class)
		};

		if ($this->misplaced($node)) {
			return $fallback;
		}

		$language  = self::language($node);
		$container = $node->parent() instanceof ContainerBlock && ! $node instanceof InlineBlock ? $node->parent()->name : '';

		if ($language !== '' || $container !== '') {
			$block = new ParsedBlock($block->name, $block->kind, $block->attributes, $block->label, $block->content, $block->outline, $language, $container);
		}

		return $this->renderer?->render($block) ?? $fallback;
	}

	/**
	 * Returns whether a block is written in a form it isn't
	 * registered for.
	 */
	private function misplaced(Node $node): bool
	{
		if ($node instanceof LeafBlock && $node->misplaced) {
			return true;
		}

		if (! $this->renderer instanceof BlockRules || ! ($node instanceof ContainerBlock || $node instanceof LeafBlock || $node instanceof InlineBlock)) {
			return false;
		}

		$forms = $this->renderer->forms($node->name);
		$kind  = match (true) {
			$node instanceof ContainerBlock => BlockKind::Container,
			$node instanceof LeafBlock      => BlockKind::Leaf,
			default                         => BlockKind::Inline
		};

		return $forms !== null && ! in_array($kind, $forms, true);
	}

	/**
	 * Returns the language the node's document is written in, or `''`.
	 */
	private static function language(Node $node): string
	{
		$root = $node;

		while ($root->parent() !== null) {
			$root = $root->parent();
		}

		$language = $root instanceof Document ? $root->data->get(self::LANGUAGE, '') : '';

		return is_string($language) ? $language : '';
	}

	/**
	 * @return array{ParsedBlock, string}
	 */
	private function container(ContainerBlock $node, ChildNodeRendererInterface $childRenderer): array
	{
		$children = $this->renderer instanceof BlockRules ? self::held($node, $this->renderer) : $node->children();
		$content  = $childRenderer->renderNodes($children);

		return [new ParsedBlock($node->name, BlockKind::Container, $node->attributes, $node->label, $content, self::outline($node)), $content];
	}

	/**
	 * Returns what a container holds of its children, or all of them when
	 * it holds anything. A paragraph is kept with only what's held in it,
	 * one to a line.
	 *
	 * @return iterable<Node>
	 */
	private static function held(ContainerBlock $node, BlockRules $contents): iterable
	{
		$holds = $contents->holds($node->name);

		if ($holds === [] || ! $node->closed) {
			return $node->children();
		}

		$held = static fn (string $name): bool => in_array($contents->fullName($name), $holds, true);
		$kept = [];

		foreach ($node->children() as $child) {
			if ($child instanceof ContainerBlock || $child instanceof LeafBlock) {
				if ($held($child->name)) {
					$kept[] = $child;
				}
			} elseif ($child instanceof Paragraph) {
				$inline = array_values(array_filter(
					[...$child->children()],
					static fn (Node $item): bool => match (true) {
						$item instanceof Image       => in_array('image', $holds, true),
						$item instanceof Link        => in_array('image', $holds, true) && $item->firstChild() instanceof Image && $item->firstChild() === $item->lastChild(),
						$item instanceof InlineBlock => $held($item->name),
						default                      => false
					}
				));

				if ($inline === []) {
					continue;
				}

				foreach ([...$child->children()] as $item) {
					$item->detach();
				}

				foreach ($inline as $index => $item) {
					if ($index > 0) {
						$child->appendChild(new Newline(Newline::SOFTBREAK));
					}

					$child->appendChild($item);
				}

				$kept[] = $child;
			}
		}

		return $kept;
	}

	/**
	 * @return array{ParsedBlock, string}
	 */
	private function leaf(LeafBlock $node): array
	{
		$content = self::escape($node->label);

		return [
			new ParsedBlock($node->name, BlockKind::Leaf, $node->attributes, $node->label, $content, self::outline($node)),
			$content === '' ? '' : "<p>{$content}</p>"
		];
	}

	/**
	 * @return array{ParsedBlock, string}
	 */
	private function inline(InlineBlock $node): array
	{
		$content = self::escape($node->label);

		return [new ParsedBlock($node->name, BlockKind::Inline, $node->attributes, $node->label, $content), $content];
	}

	/**
	 * Returns the outline `CollectOutline` gave a node, if any.
	 *
	 * @return list<array{level: int, text: string, id: string}>
	 */
	private static function outline(Node $node): array
	{
		/** @var list<array{level: int, text: string, id: string}> Set by `CollectOutline`. */
		return $node->data->get('core/outline', []);
	}

	/**
	 * Escapes text for HTML.
	 */
	private static function escape(string $text): string
	{
		return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
	}
}
