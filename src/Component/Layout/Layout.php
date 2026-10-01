<?php

/**
 * Layout component base.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Component\Layout;

use Override;
use Blush\Component\Component;
use Blush\Component\ComponentContent;

/**
 * The base for the components that wrap blocks (`group`, `grid`, `row`,
 * and `stack`; D-177, D-298, D-318). Which element one renders as is its
 * `tag`, apart from how it lays its blocks out, so `:::grid{tag=aside}` is
 * an aside in columns. A `section` or `aside` is named by the label, for screen
 * readers.
 *
 * Each concrete class promotes `tag` and `label` in its constructor, last,
 * so they come after its own props in the admin.
 */
abstract class Layout extends Component
{
	/**
	 * @inheritDoc
	 */
	public const ComponentContent CONTENT = ComponentContent::Blocks;

	// phpcs:disable PSR2.Classes.PropertyDeclaration, PHPCompatibility.Syntax.RemovedCurlyBraceArrayAccess -- PHPCS can't parse property hooks yet.
	/**
	 * The element it renders as.
	 */
	abstract public LayoutTag $tag { get; }

	/**
	 * The landmark's name, when the element is one.
	 */
	abstract public string $label { get; }
	// phpcs:enable

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function rootAttributes(): array
	{
		$label = trim($this->label);

		return $this->tag->isLandmark() && $label !== '' ? ['aria-label' => $label] : [];
	}
}
