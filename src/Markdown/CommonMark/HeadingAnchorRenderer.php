<?php

/**
 * Heading anchor renderer.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Markdown\CommonMark;

use Override;
use Closure;
use Stringable;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalink;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use League\Config\ConfigurationAwareInterface;
use League\Config\ConfigurationInterface;
use Blush\Markdown\CommonMark\Block\BlockNodeRenderer;

/**
 * Renders a heading's anchor (D-494): a link to the heading, hidden from
 * assistive technology and the tab order, as league/commonmark's does,
 * except that a heading with an id of its own (`## Title {#top}`) is
 * linked by that id, and the anchor takes none, so an author's id is the
 * one that works. An empty title is left out. Without a title of the
 * site's, `$title` gives it in the language the document is written in
 * (D-877).
 */
final class HeadingAnchorRenderer implements NodeRendererInterface, ConfigurationAwareInterface
{
	private ConfigurationInterface $config;

	/**
	 * @param ?Closure(string): string $title The title in a language (`''` for the site's).
	 */
	public function __construct(
		private readonly ?Closure $title = null
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function setConfiguration(ConfigurationInterface $configuration): void
	{
		$this->config = $configuration;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function render(Node $node, ChildNodeRendererInterface $childRenderer): Stringable
	{
		HeadingPermalink::assertInstanceOf($node);
		assert($node instanceof HeadingPermalink);

		$parent = $node->parent();
		$own    = $parent instanceof Heading ? $parent->data->get('attributes/id', '') : '';
		$attrs  = [];

		if (is_string($own) && $own !== '') {
			$attrs['href'] = "#{$own}";
		} else {
			$attrs['id']   = $this->prefixed('id_prefix') . $node->getSlug();
			$attrs['href'] = '#' . $this->prefixed('fragment_prefix') . $node->getSlug();
		}

		$attrs['class']       = (string) $this->setting('html_class');
		$attrs['aria-hidden'] = 'true';
		$attrs['tabindex']    = '-1';

		$title = $this->title === null ? (string) $this->setting('title') : ($this->title)(BlockNodeRenderer::language($node));

		if ($title !== '') {
			$attrs['title'] = $title;
		}

		return new HtmlElement('a', $attrs, htmlspecialchars((string) $this->setting('symbol')), false);
	}

	/**
	 * Returns a prefix with the dash league/commonmark puts after it.
	 */
	private function prefixed(string $key): string
	{
		$prefix = (string) $this->setting($key);

		return $prefix === '' ? '' : "{$prefix}-";
	}

	private function setting(string $key): string|int|float|bool
	{
		$value = $this->config->get("heading_permalink/{$key}");

		return is_scalar($value) ? $value : '';
	}
}
