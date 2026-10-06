<?php

/**
 * Citation directive.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Directive\Inline;

use Override;
use Blush\Directive\Directive;
use Blush\Directive\DirectiveContent;
use Blush\Directive\DirectiveView;
use Blush\Core\Framework;
use Blush\Directive\DirectiveKind;

/**
 * The title of a work, such as a book, film, or article (D-305):
 * `:cite[The Hobbit]`.
 */
final class Cite extends Directive
{
	/**
	 * @inheritDoc
	 */
	public const DirectiveContent CONTENT = DirectiveContent::Text;

	/**
	 * @inheritDoc
	 */
	public const ?DirectiveKind KIND = DirectiveKind::Inline;

	public function __construct(
		public readonly string $label = ''
	) {}

	/**
	 * Returns the text, as HTML: the content, else the label escaped.
	 */
	public function text(): string
	{
		return $this->contentOr($this->label);
	}

	/**
	 * Renders the framework's template for it, `resources/directives/cite.php`
	 * (D-382), when the theme chain has none of its own.
	 */
	#[Override]
	public function render(): DirectiveView
	{
		return $this->view(Framework::path('resources/directives/cite.php'));
	}
}
