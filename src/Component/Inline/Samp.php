<?php

/**
 * Sample output component.
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
 * Sample output from a program (D-305): `:samp[File not found.]`.
 */
final class Samp extends Component
{
	/**
	 * @inheritDoc
	 */
	public const ComponentContent CONTENT = ComponentContent::Text;

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
	 * Renders the framework's template for it, `resources/components/samp.php`
	 * (D-382), when the theme chain has none of its own.
	 */
	#[Override]
	public function render(): ComponentView
	{
		return $this->view(Framework::path('resources/components/samp.php'));
	}
}
