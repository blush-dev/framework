<?php

/**
 * Component base.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Component;

use Override;
use Blush\Translation\DomainTranslator;
use Blush\View\Renderable;
use Blush\View\ViewContext;

/**
 * A component (D-025, D-532): a reusable piece of a template, named
 * markup with typed props (by constructor promotion) and slots, that a
 * theme calls from its layouts and templates. Content never names one;
 * what content says is a directive. Themes, the site, and plugins provide
 * them, and a theme or the site overrides one by name.
 *
 * A component needn't have a class: a template in `components/` is one
 * (`components/{namespace}-{name}`), and its `$component` is a
 * `TemplateComponent`. A class is for typed props, services, and data the
 * template shouldn't work out.
 *
 * Its template is looked for in the site's views, then the theme chain's
 * (so a site's template overrides a theme's); without one, `render()`
 * gives its own markup (D-382, D-563). A theme's components have their
 * templates in the theme and need no `render()`. A plugin's, and a
 * site's that a theme may restyle, keep their default in `render()` (the
 * file they ship with, `view()`, or HTML), which a site or theme
 * template takes over.
 *
 * ```php
 * final class Card extends Component
 * {
 *     public function __construct(
 *         public readonly Entry $entry,
 *         public readonly bool $image = true
 *     ) {}
 *
 *     public function render(): ComponentView
 *     {
 *         return $this->view(__DIR__ . '/views/card.php');
 *     }
 * }
 * ```
 *
 * Its main content is `content()` and its named slots are
 * `$component->slots->footer`.
 */
abstract class Component extends Renderable
{
	/**
	 * The BEM block's prefix.
	 */
	protected const string BLOCK = 'component';

	/**
	 * Named slots, once attached.
	 */
	private ?Slots $namedSlots = null;

	// phpcs:disable -- PHPCS 4.0 doesn't tokenize property hooks yet.
	/**
	 * Named slots' HTML: `$component->slots->footer` is `''` when not
	 * filled, and `$component->slots->has('footer')` tells whether it was.
	 * Print them with `raw()`.
	 */
	public Slots $slots {
		get => $this->namedSlots ?? new Slots();
	}
	// phpcs:enable

	/**
	 * Gives the component its name, its props, its content and slots, the
	 * theme's translator, and the render it's part of. Called by `Views`
	 * when it renders one.
	 *
	 * @internal
	 * @param array<string, mixed> $props
	 */
	final public function attach(
		ComponentName $name,
		array $props,
		string $content = '',
		?Slots $slots = null,
		?DomainTranslator $translator = null,
		?ViewContext $context = null
	): void {
		$this->attachRenderable($name->name, $props, $content, $translator, $context);

		$this->namedSlots = $slots;
	}

	/**
	 * Returns the component's own markup, used when neither the site nor
	 * the theme chain has a template for it: its HTML, the template file
	 * it ships with (`view()`), or `null` (the default) when it has none,
	 * as a theme's components don't. A string is printed as it is, so
	 * escape what goes in it (`attributes()` and `html()` do).
	 */
	public function render(): string|ComponentView|null
	{
		return null;
	}

	/**
	 * Returns a template file the component ships with, for `render()`.
	 *
	 * @param string $file The template's absolute path.
	 */
	protected function view(string $file, mixed ...$data): ComponentView
	{
		/** @var array<string, mixed> $data */
		return new ComponentView($file, $data);
	}
}
