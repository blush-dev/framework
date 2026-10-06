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
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use Blush\Markdown\Directive;
use Blush\Markdown\DirectiveKind;
use Blush\Markdown\DirectiveRenderer;
use Blush\Markdown\DirectiveRules;

/**
 * Renders directive nodes through the `DirectiveRenderer`. An unknown
 * directive (or one with no renderer) renders as plain content: a
 * container's blocks, a leaf's label as a paragraph, or an inline
 * directive's text (D-026), and so does a registered one written in a
 * form it isn't registered for (`DirectiveRules::forms()`, D-530). A
 * container that holds only some things (`DirectiveRules`, D-529) renders only those: a gallery's images.
 * Its paragraphs keep only the images (or links around one) and inline
 * directives it holds, one to a line, so each image is a figure; other
 * directives it doesn't hold, and every other block, are left out. One
 * never closed (D-530) renders everything, so a missing `:::` doesn't
 * take the rest of the document with it.
 */
final readonly class DirectiveNodeRenderer implements NodeRendererInterface
{
	/**
	 * The document data key holding the language the Markdown is written
	 * in (D-459), which each directive is given.
	 */
	public const string LANGUAGE = 'blush_language';

	public function __construct(
		private ?DirectiveRenderer $renderer = null
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

		if ($this->misplaced($node)) {
			return $fallback;
		}

		$language = self::language($node);

		if ($language !== '') {
			$directive = new Directive($directive->name, $directive->kind, $directive->attributes, $directive->label, $directive->content, $directive->outline, $language);
		}

		return $this->renderer?->render($directive) ?? $fallback;
	}

	/**
	 * Returns whether a directive is written in a form it isn't
	 * registered for.
	 */
	private function misplaced(Node $node): bool
	{
		if ($node instanceof LeafDirective && $node->misplaced) {
			return true;
		}

		if (! $this->renderer instanceof DirectiveRules || ! ($node instanceof ContainerDirective || $node instanceof LeafDirective || $node instanceof InlineDirective)) {
			return false;
		}

		$forms = $this->renderer->forms($node->name);
		$kind  = match (true) {
			$node instanceof ContainerDirective => DirectiveKind::Container,
			$node instanceof LeafDirective      => DirectiveKind::Leaf,
			default                             => DirectiveKind::Inline
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
	 * @return array{Directive, string}
	 */
	private function container(ContainerDirective $node, ChildNodeRendererInterface $childRenderer): array
	{
		$children = $this->renderer instanceof DirectiveRules ? self::held($node, $this->renderer) : $node->children();
		$content  = $childRenderer->renderNodes($children);

		return [new Directive($node->name, DirectiveKind::Container, $node->attributes, $node->label, $content, self::outline($node)), $content];
	}

	/**
	 * Returns what a container holds of its children, or all of them when
	 * it holds anything. A paragraph is kept with only what's held in it,
	 * one to a line.
	 *
	 * @return iterable<Node>
	 */
	private static function held(ContainerDirective $node, DirectiveRules $contents): iterable
	{
		$holds = $contents->holds($node->name);

		if ($holds === [] || ! $node->closed) {
			return $node->children();
		}

		$held = static fn (string $name): bool => in_array($contents->fullName($name), $holds, true);
		$kept = [];

		foreach ($node->children() as $child) {
			if ($child instanceof ContainerDirective || $child instanceof LeafDirective) {
				if ($held($child->name)) {
					$kept[] = $child;
				}
			} elseif ($child instanceof Paragraph) {
				$inline = array_values(array_filter(
					[...$child->children()],
					static fn (Node $item): bool => match (true) {
						$item instanceof Image           => in_array('image', $holds, true),
						$item instanceof Link            => in_array('image', $holds, true) && $item->firstChild() instanceof Image && $item->firstChild() === $item->lastChild(),
						$item instanceof InlineDirective => $held($item->name),
						default                          => false
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
	 * @return array{Directive, string}
	 */
	private function leaf(LeafDirective $node): array
	{
		$content = self::escape($node->label);

		return [
			new Directive($node->name, DirectiveKind::Leaf, $node->attributes, $node->label, $content, self::outline($node)),
			$content === '' ? '' : "<p>{$content}</p>"
		];
	}

	/**
	 * @return array{Directive, string}
	 */
	private function inline(InlineDirective $node): array
	{
		$content = self::escape($node->label);

		return [new Directive($node->name, DirectiveKind::Inline, $node->attributes, $node->label, $content), $content];
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
	 * Escapes text for HTML.
	 */
	private static function escape(string $text): string
	{
		return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
	}
}
