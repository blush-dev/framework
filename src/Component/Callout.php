<?php

/**
 * Callout component.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Component;

use Override;
use Blush\View\Escaper;

/**
 * A note set apart from the text (D-113, D-195), with an optional title
 * (the label) and a tone:
 *
 * ```md
 * :::callout[Heads up]{tone=warning}
 * Back up your site first.
 * :::
 * ```
 *
 * `title` is an older name for the label, kept for 1.x content (D-078).
 */
final class Callout extends Component
{
	/**
	 * @inheritDoc
	 */
	public const ComponentContent CONTENT = ComponentContent::Blocks;

	public function __construct(
		public readonly CalloutTone $tone = CalloutTone::Note,
		public readonly string $label = '',
		public readonly string $title = ''
	) {}

	/**
	 * Returns the callout's title, as HTML: the label, else `title`,
	 * escaped, or `''`. (Its content is its body.)
	 */
	public function heading(): string
	{
		return Escaper::html(trim($this->label) !== '' ? trim($this->label) : trim($this->title));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function modifiers(): array
	{
		return [$this->tone->value];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function rootAttributes(): array
	{
		return ['role' => 'note'];
	}
}
