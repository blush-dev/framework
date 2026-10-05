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
use League\CommonMark\Node\Block\Document;
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

		$language = self::language($node);

		if ($language !== '') {
			$directive = new Directive($directive->name, $directive->kind, $directive->attributes, $directive->label, $directive->content, $directive->outline, $language);
		}

		return $this->renderer?->render($directive) ?? $fallback;
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
		$content = $childRenderer->renderNodes($node->children());

		return [new Directive($node->name, DirectiveKind::Container, $node->attributes, $node->label, $content, self::outline($node)), $content];
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
