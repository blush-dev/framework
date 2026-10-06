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

namespace Blush\Component;

use Override;
use Blush\Core\Framework;
use Blush\Icon\IconName;
use Blush\Icon\Icons;
use Blush\Markdown\DirectiveKind;
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
 * nothing renders. Its Default is the main, filled look; its `secondary`
 * variant (D-266) is for an action beside the main one.
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
	 * @inheritDoc
	 */
	public const ?DirectiveKind KIND = DirectiveKind::Inline;

	/**
	 * @inheritDoc
	 */
	public const array VARIANTS = ['secondary'];

	/**
	 * Whether the icon exists, so it's shown.
	 */
	public readonly bool $hasIcon;

	public function __construct(
		Icons $icons,
		ThemeResolver $themes,
		#[LinkProp] public readonly string $url = '',
		public readonly string $label = '',
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

	/**
	 * Returns the button's text, as HTML: the content, else the label
	 * escaped.
	 */
	public function text(): string
	{
		return $this->contentOr($this->label);
	}

	/**
	 * Returns whether the text is shown (it is unless only the icon is).
	 */
	public function showsText(): bool
	{
		return ! $this->isIconOnly();
	}

	/**
	 * Returns whether the icon is shown before the text, or alone.
	 */
	public function showsIconBefore(): bool
	{
		return $this->hasIcon && ($this->isIconOnly() || $this->iconPosition === IconPosition::Start);
	}

	/**
	 * Returns whether the icon is shown after the text.
	 */
	public function showsIconAfter(): bool
	{
		return $this->hasIcon && ! $this->isIconOnly() && $this->iconPosition === IconPosition::End;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function modifiers(): array
	{
		return $this->isIconOnly() ? ['icon-only'] : [];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	protected function rootAttributes(): array
	{
		// With only the icon, the label names the link.
		return $this->isIconOnly() ? ['aria-label' => $this->label, 'title' => $this->label] : [];
	}

	/**
	 * Renders the framework's template for it, `resources/components/button.php`
	 * (D-382), when the theme chain has none of its own.
	 */
	#[Override]
	public function render(): ComponentView
	{
		return $this->view(Framework::path('resources/components/button.php'));
	}
}
