<?php

/**
 * Abbreviation component.
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

/**
 * An abbreviation with its expansion as `title` (D-180, D-195):
 * `:abbr[CMS]{title="content management system"}`. The label is its text.
 */
final class Abbr extends Component
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
	 * Returns the abbreviation, as HTML: the content, else the label
	 * escaped.
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
}
