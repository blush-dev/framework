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
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
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
 * except those that belong on the `<img>`. A paragraph of such lines, an
 * image on each, as a gallery's are written (D-528), is a figure for
 * each, without the paragraph or its line breaks. Inside a `:::figure`
 * container (D-267), which is already the figure, the images render on
 * their own, without a paragraph or a figure around them. Every other
 * paragraph renders as usual, images beside other text or each other on
 * one line included.
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

		$lines = self::lines($node);

		if ($lines === null) {
			return $this->paragraphs->render($node, $childRenderer);
		}

		$html = [];

		foreach ($lines as $line) {
			// The container is the figure: the image stands alone in it.
			$html[] = self::inFigure($node)
				? $childRenderer->renderNodes([$line])
				: self::figure($line, $childRenderer);
		}

		return implode("\n", $html);
	}

	/**
	 * Returns the images (or links around one) a paragraph is made of, one
	 * to a line, or `null` when it has anything else, or two on a line.
	 *
	 * @return non-empty-list<Image|Link>|null
	 */
	private static function lines(Paragraph $node): ?array
	{
		$lines = [];
		$open  = true;

		foreach ($node->children() as $child) {
			if ($child instanceof Newline) {
				$open = true;
			} elseif ($child instanceof Text && trim($child->getLiteral()) === '') {
				continue;
			} elseif ($open && ($child instanceof Image || ($child instanceof Link && self::linksImage($child)))) {
				$lines[] = $child;
				$open    = false;
			} else {
				return null;
			}
		}

		return $lines === [] ? null : $lines;
	}

	/**
	 * Returns whether a link holds nothing but an image.
	 */
	private static function linksImage(Link $link): bool
	{
		$image = $link->firstChild();

		return $image instanceof Image && $image === $link->lastChild();
	}

	/**
	 * Renders an image (or a link around one) as a figure.
	 */
	private static function figure(Image|Link $node, ChildNodeRendererInterface $childRenderer): HtmlElement
	{
		$link  = $node instanceof Link ? $node : null;
		$image = $link?->firstChild() ?? $node;

		if (! $image instanceof Image) {
			throw new InvalidArgumentException('A figure needs an image.');
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

		$contents = $childRenderer->renderNodes([$node]);

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
