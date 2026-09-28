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

use Blush\Translation\Translator;
use Blush\View\Escaper;

/**
 * A component (D-025, D-195): typed props through constructor promotion,
 * and the template (`components/{key}`) that renders it. The template
 * gets the component as `$component`, so its props and computed values
 * are `$component->tone` and `$component->heading()`.
 *
 * ```php
 * final class Card extends Component
 * {
 *     public function __construct(
 *         public readonly string $title,
 *         public readonly int $columns = 2
 *     ) {}
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
 * modifiers. Its main content is `content()` and its named slots are
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
	 * The translator with the theme's catalogs.
	 */
	private ?Translator $translator = null;

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
	 * Gives the component its name, its props, its content and slots, and
	 * the theme's translator. Called by `Views` when it renders one.
	 *
	 * @internal
	 * @param array<string, mixed> $props
	 */
	final public function attach(
		ComponentName $name,
		array $props,
		string $content = '',
		?Slots $slots = null,
		?Translator $translator = null
	): void {
		$this->componentName = $name;
		$this->props         = $props;
		$this->class         = is_string($props['class'] ?? null) ? trim($props['class']) : '';
		$this->id            = is_string($props['id'] ?? null) ? trim($props['id']) : '';
		$this->content       = $content;
		$this->namedSlots    = $slots;
		$this->translator    = $translator;
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
	 * Returns the root element's classes: the block, its modifiers
	 * (`component-callout--warning`), and the `class` prop.
	 */
	public function classes(): string
	{
		$block     = $this->block();
		$modifiers = array_map(static fn (string $modifier): string => "{$block}--{$modifier}", $this->modifiers());

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
	 * Returns the block's modifiers for its current props, such as
	 * `['warning']` for `component-callout--warning`.
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
	 * Returns the content, or else `$text` escaped as HTML: for a method
	 * that names the content for its role, such as a caption, so it
	 * works whether the text came as the content or as a prop.
	 */
	protected function contentOr(string $text): string
	{
		return $this->content !== '' ? $this->content : Escaper::html(trim($text));
	}

	/**
	 * Translates a message from the theme's catalogs, like a template's
	 * `$template->t()`. Without a translator (a component built outside
	 * `Views`), it returns the key.
	 */
	protected function t(string $key, string|int|float ...$params): string
	{
		/** @var array<string, string|int|float> $params */
		return $this->translator?->translate($key, $params, 'theme') ?? $key;
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
