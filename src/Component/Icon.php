<?php

/**
 * Icon component.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Component;

use DOMException;
use Dom\XMLDocument;
use Override;
use Blush\Icon\IconName;
use Blush\Icon\Icons;
use Blush\Theme\ThemeException;
use Blush\Theme\ThemeResolver;

/**
 * An icon, as inline SVG (D-187): `:icon[Home]{name=house}`, or
 * `:icon[]{name=heart}` for one that's only decoration. `name` is an
 * icon name (`house` is the core `blush/house`; `jtcom/github` a theme's),
 * found for the request's theme chain by `Icons`.
 *
 * It's `1em` square and drawn in the text color. Without a label it's
 * hidden from screen readers (`aria-hidden`); with one it's an image
 * named by the label (`role="img"`, `aria-label`). Nothing renders for
 * an unknown icon. The template prints `$component->markup()`, with the
 * block class, the `class` prop, and the `id`.
 */
final class Icon extends Component
{
	/**
	 * @inheritDoc
	 */
	public const ComponentContent CONTENT = ComponentContent::Text;

	/**
	 * The icon's SVG file contents, or `null` when it wasn't found.
	 */
	private readonly ?string $svg;

	public function __construct(
		Icons $icons,
		ThemeResolver $themes,
		public readonly string $name = '',
		public readonly string $label = ''
	) {
		$parsed = IconName::parse($name);

		try {
			$chain = $themes->current();
		} catch (ThemeException) {
			// A broken theme chain is reported when the page renders.
			$chain = null;
		}

		$this->svg = $parsed === null || $chain === null ? null : $icons->svg($parsed, $chain);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function shouldRender(): bool
	{
		return $this->markup() !== '';
	}

	/**
	 * Returns the icon's inline SVG with the `component-icon` class (and
	 * any others), its size, and its accessibility attributes, or `''`
	 * when it wasn't found or isn't an SVG.
	 */
	public function markup(): string
	{
		if ($this->svg === null) {
			return '';
		}

		try {
			$document = XMLDocument::createFromString($this->svg, LIBXML_NONET);
		} catch (DOMException) {
			return '';
		}

		$root = $document->documentElement;

		if ($root === null || $root->localName !== 'svg') {
			return '';
		}

		$label = trim($this->label);

		$root->setAttribute('width', '1em');
		$root->setAttribute('height', '1em');
		$root->setAttribute('class', $this->classes());

		if ($this->id !== '') {
			$root->setAttribute('id', $this->id);
		}

		$root->setAttribute('focusable', 'false');

		if ($label === '') {
			$root->removeAttribute('role');
			$root->removeAttribute('aria-label');
			$root->setAttribute('aria-hidden', 'true');
		} else {
			$root->removeAttribute('aria-hidden');
			$root->setAttribute('role', 'img');
			$root->setAttribute('aria-label', $label);
		}

		return (string) $document->saveXml($root);
	}
}
