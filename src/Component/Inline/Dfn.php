<?php

/**
 * Definition component.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Component\Inline;

use Override;
use Blush\Component\Component;
use Blush\Component\ComponentContent;
use Blush\Component\ComponentView;
use Blush\Core\Framework;

/**
 * The defining instance of a term, in the sentence that defines it
 * (D-305): `:dfn[Blush] is a flat-file CMS.` When the text isn't the term
 * as written, `title` is: `:dfn[flat files]{title="flat-file storage"}`.
 */
final class Dfn extends Component
{
	/**
	 * @inheritDoc
	 */
	public const ComponentContent CONTENT = ComponentContent::Text;

	public function __construct(
		public readonly string $title = '',
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
	 * @inheritDoc
	 */
	#[Override]
	protected function rootAttributes(): array
	{
		return ['title' => trim($this->title)];
	}

	/**
	 * Renders the framework's template for it, `resources/components/dfn.php`
	 * (D-382), when the theme chain has none of its own.
	 */
	#[Override]
	public function render(): ComponentView
	{
		return $this->view(Framework::path('resources/components/dfn.php'));
	}
}
