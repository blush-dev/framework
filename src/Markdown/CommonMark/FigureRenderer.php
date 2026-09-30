<?php

/**
 * Markdown figure renderer.
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
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\Block\ParagraphRenderer;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\CommonMark\Util\Xml;
use Blush\Markdown\CommonMark\Directive\ContainerDirective;

/**
 * Renders a paragraph holding nothing but an image (or a link around an
 * image) as a `<figure>` instead of a `<p>`, as 1.x did (D-078): the
 * image's title becomes the `<figcaption>`, and attributes given to the
 * image (or else the link), such as `{.stretch-wide}`, go on the figure,
 * except those that belong on the `<img>`. Inside a `:::figure` container
 * (D-267), which is already the figure, the image renders on its own,
 * without a paragraph or a figure around it. Every other paragraph
 * renders as usual.
 */
final readonly class FigureRenderer implements NodeRendererInterface
{
	/**
	 * Attributes that stay on the `<img>`.
	 *
	 * @var list<string>
	 */
	private const array IMAGE_ATTRIBUTES = ['src', 'alt', 'width', 'height', 'srcset', 'sizes', 'loading', 'decoding', 'fetchpriority'];

	public function __construct(private ParagraphRenderer $paragraphs = new ParagraphRenderer())
	{}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function render(Node $node, ChildNodeRendererInterface $childRenderer): Stringable|string|null
	{
		if (! $node instanceof Paragraph) {
			throw new InvalidArgumentException(sprintf('%s renders paragraphs; %s given.', self::class, $node::class));
		}

		$child = $node->firstChild();
		$link  = $child instanceof Link && $child === $child->parent()?->lastChild() ? $child : null;
		$image = $link === null ? $child : $link->firstChild();

		if (! $image instanceof Image || $child !== $node->lastChild() || ($link !== null && $image !== $link->lastChild())) {
			return $this->paragraphs->render($node, $childRenderer);
		}

		// The container is the figure: the image stands alone in it.
		if (self::inFigure($node)) {
			return $childRenderer->renderNodes($node->children());
		}

		$figure = [];

		foreach ($link === null ? [$image] : [$image, $link] as $source) {
			$attributes = self::attributes($source);
			$figure     = array_diff_key($attributes, array_flip(self::IMAGE_ATTRIBUTES));

			if ($figure !== []) {
				$source->data->set('attributes', array_intersect_key($attributes, array_flip(self::IMAGE_ATTRIBUTES)));
				break;
			}
		}

		$caption = $image->getTitle();
		$image->setTitle(null);

		$contents = $childRenderer->renderNodes($node->children());

		if ($caption !== null && $caption !== '') {
			$contents .= "\n" . new HtmlElement('figcaption', [], Xml::escape($caption));
		}

		return new HtmlElement('figure', $figure, $contents);
	}

	/**
	 * Returns whether a paragraph is directly inside a `:::figure`
	 * container.
	 */
	private static function inFigure(Paragraph $node): bool
	{
		$parent = $node->parent();

		return $parent instanceof ContainerDirective && in_array($parent->name, ['figure', 'blush/figure'], true);
	}

	/**
	 * Returns a node's HTML attributes.
	 *
	 * @return array<string, string|string[]|bool>
	 */
	private static function attributes(Node $node): array
	{
		$attributes = $node->data->get('attributes', []);
		$valid      = [];

		foreach (is_array($attributes) ? $attributes : [] as $name => $value) {
			if (! is_string($name)) {
				continue;
			}

			if (is_string($value) || is_bool($value)) {
				$valid[$name] = $value;
			} elseif (is_array($value)) {
				$strings = array_values(array_filter($value, is_string(...)));

				if (count($strings) === count($value)) {
					$valid[$name] = $strings;
				}
			}
		}

		return $valid;
	}
}
