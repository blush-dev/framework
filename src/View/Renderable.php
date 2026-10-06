<?php

/**
 * Renderable base.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View;

use Blush\Translation\DomainTranslator;

/**
 * What directives and components have in common (D-532): they're two
 * things, but both are a class with typed props (by constructor
 * promotion) that draws one root element, so they share the props, the
 * content, the root element's attributes, and the theme's text.
 *
 * Every one has the `class` and `id` props (`.class` and `#id` in
 * Markdown), and its root element prints them with `attributes()`, which
 * also names its BEM block (`directive-{name}` or `component-{name}`,
 * D-182) and modifiers. Props are plain text: print them with `e()` or
 * `attr()`. Content (`content()` and methods that name it for its role,
 * such as a figure's `caption()`) is HTML: print it with `raw()`.
 */
abstract class Renderable
{
	/**
	 * The BEM block's prefix: `directive` or `component`.
	 */
	protected const string BLOCK = '';

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
	 * Its name without the namespace, once it's attached.
	 */
	private string $shortName = '';

	/**
	 * Every prop it was given, including those its constructor doesn't
	 * take.
	 *
	 * @var array<string, mixed>
	 */
	private array $props = [];

	/**
	 * The translator bound to the theme chain's domains and then its own
	 * namespace's (D-451).
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
	 * Returns the main content, as HTML (`''` when there's none). Print it
	 * with `raw()`.
	 */
	public function content(): string
	{
		return $this->content;
	}

	/**
	 * Returns the view it renders, or `null` for the one its name gives.
	 */
	public function template(): ?string
	{
		return null;
	}

	/**
	 * Returns whether it renders anything at all.
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
	 * Returns its BEM block class: `directive-{name}` or
	 * `component-{name}`.
	 */
	public function block(): string
	{
		return static::BLOCK . '-' . ($this->shortName === '' ? self::fallbackName(static::class) : $this->shortName);
	}

	/**
	 * Returns the root element's classes: the block, its modifiers, and
	 * the `class` prop.
	 */
	public function classes(): string
	{
		$block     = $this->block();
		$modifiers = array_map(static fn (string $modifier): string => "{$block}--{$modifier}", array_values(array_unique($this->blockModifiers())));

		return implode(' ', array_filter([$block, ...$modifiers, $this->class], static fn (string $class): bool => $class !== ''));
	}

	/**
	 * Returns the root element's attributes, escaped and ready to print:
	 * `class`, `id`, and its own (see `rootAttributes()`), plus any given
	 * here. A `class` given here is added to the others. `null`, `false`,
	 * and `''` values are left out, and `true` prints the name alone.
	 *
	 * ```php
	 * <aside <?= $directive->attributes(['data-open' => true]) ?>>
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
	 * Gives it its name, props, content, the theme's translator, and the
	 * render it's part of.
	 *
	 * @param array<string, mixed> $props
	 */
	final protected function attachRenderable(
		string $shortName,
		array $props,
		string $content,
		?DomainTranslator $translator,
		?ViewContext $context
	): void {
		$this->shortName   = $shortName;
		$this->props       = $props;
		$this->class       = is_string($props['class'] ?? null) ? trim($props['class']) : '';
		$this->id          = is_string($props['id'] ?? null) ? trim($props['id']) : '';
		$this->content     = $content;
		$this->translator  = $translator;
		$this->viewContext = $context;
	}

	/**
	 * Returns every modifier its block gets: its own (`modifiers()`) by
	 * default.
	 *
	 * @return list<string>
	 */
	protected function blockModifiers(): array
	{
		return $this->modifiers();
	}

	/**
	 * Returns the block's modifiers for its current props, such as
	 * `['icon-only']` for `directive-button--icon-only`.
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
	 * Returns the render it's part of: the page's path and locale, and its
	 * `Head`. `null` for one built outside `Views`.
	 */
	protected function context(): ?ViewContext
	{
		return $this->viewContext;
	}

	/**
	 * Returns the page's locale, so a translation's numbers and dates are
	 * in its language (D-466), else `$site` (the site's): for one built
	 * outside `Views`, or a page in the site's language.
	 */
	protected function locale(string $site): string
	{
		$locale = $this->viewContext->locale ?? '';

		return $locale === '' ? $site : $locale;
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
	 * template's `$template->t()`, and then from its own extension's
	 * (D-451), so a plugin can ship its text and a theme can reword it.
	 * Without a translator (one built outside `Views`), it returns the
	 * key. Text is in the page's locale, so a translation's directives and
	 * components speak its language (D-462).
	 */
	protected function t(string $key, string|int|float ...$params): string
	{
		$locale = $this->viewContext->locale ?? '';

		/** @var array<string, string|int|float> $params */
		return $this->translator?->translate($key, $params, $locale === '' ? null : $locale) ?? $key;
	}

	/**
	 * Returns a name for one that isn't attached: its class's short name
	 * in kebab case.
	 */
	private static function fallbackName(string $class): string
	{
		$short = substr((string) strrchr('\\' . $class, '\\'), 1);

		return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $short));
	}
}
