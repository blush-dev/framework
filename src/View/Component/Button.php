<?php

/**
 * Button component.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View\Component;

use Override;
use Blush\Icon\IconName;
use Blush\Icon\Icons;
use Blush\Theme\ThemeException;
use Blush\Theme\ThemeResolver;
use Blush\View\Escaper;

/**
 * A link styled as a button (D-175, D-189):
 * `::button[Get started]{url=/start icon=arrow-right iconPosition=end}`.
 * It's an `<a>`, since a button in content goes somewhere; a `<button>`
 * for scripts may come later.
 *
 * The label is its text and is required, as is a safe `url` (a
 * `javascript:` link isn't one, by the `url()` escaper's rules), or
 * nothing renders. `variant` is `primary` (the default) or `secondary`.
 * `icon` names any icon, shown before the text
 * (`iconPosition=start`) or after it (`end`), and is decorative, since
 * the text names the button. `iconOnly` shows just the icon; the label
 * then names the link (`aria-label`, and `title` for a tooltip), so it
 * works in any theme. Without the icon, `iconOnly` is ignored.
 */
final class Button extends Component
{
	/**
	 * @inheritDoc
	 */
	public const ComponentContent CONTENT = ComponentContent::Text;

	/**
	 * Whether the icon exists, so it's shown.
	 */
	public readonly bool $hasIcon;

	public function __construct(
		Icons $icons,
		ThemeResolver $themes,
		#[LinkProp] public readonly string $url = '',
		public readonly string $label = '',
		public readonly ButtonVariant $variant = ButtonVariant::Primary,
		public readonly string $icon = '',
		public readonly IconPosition $iconPosition = IconPosition::Start,
		public readonly bool $iconOnly = false
	) {
		$name = IconName::parse($icon);

		try {
			$this->hasIcon = $name !== null && $icons->file($name, $themes->current()) !== null;
		} catch (ThemeException) {
			$this->hasIcon = false;
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function shouldRender(): bool
	{
		return Escaper::url($this->url) !== '' && trim($this->label) !== '';
	}

	/**
	 * Returns whether only the icon is shown.
	 */
	public function isIconOnly(): bool
	{
		return $this->iconOnly && $this->hasIcon;
	}
}
