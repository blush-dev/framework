<?php

/**
 * Component directives.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View;

use Closure;
use Override;
use Blush\Container\Attributes\Defer;
use Blush\Markdown\Directive;
use Blush\Markdown\DirectiveRenderer;
use Blush\Theme\ThemeResolver;
use Blush\View\Component\Slots;

/**
 * Renders Markdown directives as the components of the same name (D-026),
 * with the theme chain of the request being rendered: `:::callout{tone=info}`
 * is the `blush/callout` component with `tone` and the block's HTML as
 * `$slot`, and `::acme/tabs` is `acme/tabs`. Only core components have
 * short names (D-171). A directive's `[label]` is also given as the
 * `label` prop. An unknown name returns `null`, so the directive renders
 * as plain content.
 *
 * Components rendered this way get a bare context: what they add to the
 * `Head` doesn't reach the page.
 *
 * The view factory is resolved on first use, since it depends (through
 * the content repository) on the Markdown parser that depends on this.
 */
final readonly class ComponentDirectives implements DirectiveRenderer
{
	/**
	 * @param Closure(): ViewFactory $views
	 */
	public function __construct(
		#[Defer(ViewFactory::class)] private Closure $views,
		private ThemeResolver $themes
	) {}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function render(Directive $directive): ?string
	{
		$factory = ($this->views)();
		$views   = $factory->forChain($this->themes->current());

		if (! $views->hasComponent($directive->name)) {
			return null;
		}

		$props = $directive->attributes;

		if ($directive->label !== '') {
			$props['label'] ??= $directive->label;
		}

		return $views->component($directive->name, $props, $directive->content, new Slots(), $factory->fragment());
	}
}
