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

use Blush\Translation\DomainTranslator;
use Blush\View\Escaper;
use Blush\View\ViewContext;

/**
 * A component (D-025, D-195): typed props through constructor promotion,
 * and the template (`components/{key}`) that renders it. The template
 * gets the component as `$component`, so its props and computed values
 * are `$component->label` and `$component->heading()`.
 *
 * Every component class renders itself by default (D-382): `render()`
 * returns its HTML, or the template file it ships with (`view()`), or
 * `null` when it has no markup of its own and needs a theme's template.
 * A template in the theme chain (or the site's views) wins over it, so
 * themes restyle a component by giving it a template.
 *
 * ```php
 * final class Card extends Component
 * {
 *     public function __construct(
 *         public readonly string $title,
 *         public readonly int $columns = 2
 *     ) {}
 *
 *     public function render(): ComponentView
 *     {
 *         return $this->view(__DIR__ . '/views/card.php');
 *     }
 * }
 * ```
 *
 * The component is built through the container, so its constructor can
 * also ask for services. Props given as strings (from Markdown directives)
 * are cast to a parameter's `int`, `float`, `bool`, or backed enum type.
 *
 * Every component has the `class` and `id` props (`.class` and `#id` in
 * Markdown), and its root element prints them with `attributes()`, which
 * also names the component's BEM block (`component-{name}`, D-182) and
 * modifiers. Every component has the `variant` prop too (D-266): the
 * variants it declares are its `VARIANTS`, `$component->variant` names
 * the one it renders (`'default'` for none), and a variant adds its
 * modifier (`component-callout--warning`). So no component takes a
 * `variant` parameter of its own. Its main content is `content()` and its named slots are
 * `$slots->footer`; a component can name the content for its role, such
 * as a figure's `caption()`.
 *
 * Props are plain text: print them with `e()` or `attr()`. Content
 * (`content()`, slots, and methods such as `caption()` and `text()`) is
 * HTML: print it with `raw()`.
 */
abstract class Component
{
	/**
	 * What the component wraps, which decides how the admin's inserter
	 * writes it in Markdown (D-172).
	 */
	public const ComponentContent CONTENT = ComponentContent::None;

	/**
	 * The variants it declares, by name, besides Default (D-266). Their
	 * text is in the component's namespace's catalog.
	 *
	 * @var list<string>
	 */
	public const array VARIANTS = [];

	/**
	 * What a container holds, when it holds only some things (D-314): the
	 * keys the admin's inserter uses, `image` for a Markdown image or a
	 * component's full name. Empty for anything. The admin offers only
	 * these inside it; the site renders whatever is there.
	 *
	 * @var list<string>
	 */
	public const array HOLDS = [];

	/**
	 * The attributes `html()` escapes as URLs.
	 *
	 * @var list<string>
	 */
	private const array URL_ATTRIBUTES = ['action', 'cite', 'data', 'formaction', 'href', 'poster', 'src'];

	/**
	 * Extra classes for the root element, from the `class` prop.
	 */
	public private(set) string $class = '';

	/**
	 * The root element's ID, from the `id` prop, or `''`.
	 */
	public private(set) string $id = '';

	/**
	 * The variant it renders: a variant's name, or `'default'`.
	 */
	public private(set) string $variant = Variant::DEFAULT;

	/**
	 * The variant it renders, or `null` for Default.
	 */
	private ?Variant $variantInfo = null;

	/**
	 * The component's name, once it's attached.
	 */
	private ?ComponentName $componentName = null;

	/**
	 * Every prop it was given, including those its constructor doesn't
	 * take.
	 *
	 * @var array<string, mixed>
	 */
	private array $props = [];

	/**
	 * The translator bound to the theme chain's domains and then the
	 * component's own (D-451).
	 */
	private ?DomainTranslator $translator = null;

	/**
	 * The render it's part of.
	 */
	private ?ViewContext $viewContext = null;

	/**
	 * The main content's HTML.
	 */
	private string $content = '';

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
	 * theme's translator, the render it's part of, and the variant it
	 * renders (`null` for Default). Called by `Views` when it renders one.
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
		?ViewContext $context = null,
		?Variant $variant = null
	): void {
		$this->componentName = $name;
		$this->props         = $props;
		$this->class         = is_string($props['class'] ?? null) ? trim($props['class']) : '';
		$this->id            = is_string($props['id'] ?? null) ? trim($props['id']) : '';
		$this->content       = $content;
		$this->namedSlots    = $slots;
		$this->translator    = $translator;
		$this->viewContext   = $context;
		$this->variantInfo   = $variant;
		$this->variant       = $variant->name ?? Variant::DEFAULT;
	}

	/**
	 * Returns whether it renders a variant: `isVariant('warning')`.
	 */
	public function isVariant(string $name): bool
	{
		return $this->variant === $name;
	}

	/**
	 * Returns the main content, as HTML (`''` when there's none): in
	 * Markdown, a `:::` block's content or the escaped label; from a
	 * template, what `->content()` gave. Print it with `raw()`.
	 */
	public function content(): string
	{
		return $this->content;
	}

	/**
	 * Returns the view the component renders, or `null` for
	 * `components/{key}`.
	 */
	public function template(): ?string
	{
		return null;
	}

	/**
	 * Returns the component's own markup, used when the theme chain has
	 * no template for it: its HTML, the template file it ships with
	 * (`view()`), or `null` when it has none and needs a theme's
	 * template. A string is printed as it is, so escape what goes in it
	 * (`attributes()` and `html()` do).
	 */
	abstract public function render(): string|ComponentView|null;

	/**
	 * Returns whether the component renders anything at all.
	 */
	public function shouldRender(): bool
	{
		return true;
	}

	/**
	 * Returns a prop as given, including one its constructor doesn't take
	 * (such as `data-n`), or `$default`.
	 */
	public function prop(string $name, mixed $default = null): mixed
	{
		return $this->props[$name] ?? $default;
	}

	/**
	 * Returns the component's BEM block class: `component-{name}`.
	 */
	public function block(): string
	{
		return 'component-' . ($this->componentName === null ? self::fallbackName(static::class) : $this->componentName->name);
	}

	/**
	 * Returns the root element's classes: the block, its variant's
	 * modifier (`component-callout--warning`) and its own, and the `class`
	 * prop.
	 */
	public function classes(): string
	{
		$block     = $this->block();
		$own       = $this->variantInfo === null ? $this->modifiers() : [$this->variantInfo->modifier(), ...$this->modifiers()];
		$modifiers = array_map(static fn (string $modifier): string => "{$block}--{$modifier}", array_values(array_unique($own)));

		return implode(' ', array_filter([$block, ...$modifiers, $this->class], static fn (string $class): bool => $class !== ''));
	}

	/**
	 * Returns the root element's attributes, escaped and ready to print:
	 * `class`, `id`, and the component's own (see `rootAttributes()`),
	 * plus any given here. A `class` given here is added to the others.
	 * `null`, `false`, and `''` values are left out, and `true` prints
	 * the name alone.
	 *
	 * ```php
	 * <aside <?= $component->attributes(['data-open' => true]) ?>>
	 * ```
	 *
	 * @param array<string, string|int|float|bool|null> $extra
	 */
	public function attributes(array $extra = []): string
	{
		$class      = trim($this->classes() . ' ' . (is_string($extra['class'] ?? null) ? $extra['class'] : ''));
		$attributes = ['id' => $this->id, ...$this->rootAttributes(), ...$extra];

		unset($attributes['class']);

		return self::html(['class' => $class, ...$attributes]);
	}

	/**
	 * Returns attributes as HTML, escaped: `null`, `false`, and `''`
	 * values are left out, and `true` prints the name alone. URL
	 * attributes (`src`, `href`, and so on) are escaped like `url()`, so
	 * one with an unsafe scheme (`javascript:`) is left out too.
	 *
	 * @param array<string, string|int|float|bool|null> $attributes
	 */
	public static function html(array $attributes): string
	{
		$html = [];

		foreach ($attributes as $name => $value) {
			if ($value === true) {
				$html[] = Escaper::attr($name);

				continue;
			}

			$escaped = match (true) {
				$value === null || $value === false                => '',
				in_array(strtolower($name), self::URL_ATTRIBUTES, true) => Escaper::url((string) $value),
				default                                            => Escaper::attr($value)
			};

			if ($escaped !== '') {
				$html[] = Escaper::attr($name) . '="' . $escaped . '"';
			}
		}

		return implode(' ', $html);
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

	/**
	 * Returns the block's modifiers for its current props, besides its
	 * variant's, such as `['icon-only']` for `component-button--icon-only`.
	 *
	 * @return list<string>
	 */
	protected function modifiers(): array
	{
		return [];
	}

	/**
	 * Returns the root element's own attributes, besides `class` and
	 * `id`, such as a callout's `role`.
	 *
	 * @return array<string, string|int|float|bool|null>
	 */
	protected function rootAttributes(): array
	{
		return [];
	}

	/**
	 * Returns the render the component is part of: the page's path and
	 * locale, and its `Head`. `null` for a component built outside
	 * `Views`.
	 */
	protected function context(): ?ViewContext
	{
		return $this->viewContext;
	}

	/**
	 * Returns the content, or else `$text` escaped as HTML: for a method
	 * that names the content for its role, such as a caption, so it
	 * works whether the text came as the content or as a prop.
	 */
	protected function contentOr(string $text): string
	{
		return $this->content !== '' ? $this->content : Escaper::html(trim($text));
	}

	/**
	 * Translates a message from the theme chain's catalogs, like a
	 * template's `$template->t()`, and then from the component's own
	 * extension's (D-451), so a plugin's component can ship its text and a
	 * theme can reword it. Without a translator (a component built outside
	 * `Views`), it returns the key.
	 */
	protected function t(string $key, string|int|float ...$params): string
	{
		/** @var array<string, string|int|float> $params */
		return $this->translator?->translate($key, $params) ?? $key;
	}

	/**
	 * Returns a name for a component that isn't attached: its class's
	 * short name in kebab case.
	 */
	private static function fallbackName(string $class): string
	{
		$short = substr((string) strrchr('\\' . $class, '\\'), 1);

		return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $short));
	}
}
