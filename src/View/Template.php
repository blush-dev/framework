<?php

/**
 * Template.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View;

use BackedEnum;
use Closure;
use DateTimeInterface;
use Stringable;
use Blush\Cache\CacheException;
use Blush\Cache\CacheNamespace;
use Blush\Clock\DateFormat;
use Blush\Clock\DateStyle;
use Blush\Menu\Menu;
use Blush\Routing\UrlGenerationException;
use Blush\Component\PendingComponent;
use Blush\Block\PendingBlock;

/**
 * `$template` inside a template file: the small API templates use to build
 * pages (see `theming.md`). A template file runs in an isolated scope with
 * its data as variables, and can reach only this class's public methods
 * (D-158). It's the API for every view engine (D-502); methods marked
 * `#[ReturnsHtml]` return rendered HTML, to print as it is.
 *
 * ```php
 * <?php $template->layout('base') ?>
 *
 * <article class="entry">
 *     <h1><?= e($entry->title) ?></h1>
 *     <?= raw($entry->content()) ?>
 * </article>
 * ```
 */
final class Template
{
	/**
	 * @param TemplateState $state What it leaves for `Views`: its layout and open sections (D-815).
	 */
	public function __construct(
		private readonly Views $views,
		private readonly ViewContext $context,
		private readonly TemplateState $state = new TemplateState()
	) {}

	/**
	 * Wraps this template in a layout (`layouts/{name}`, so `shells/wide`
	 * is `layouts/shells/wide`), with extra data for it. The template's
	 * output becomes the layout's `content` section.
	 */
	public function layout(string $name, mixed ...$data): void
	{
		$this->state->layout = ["layouts/{$name}", self::named($data)];
	}

	/**
	 * Sets a section to rendered HTML. PHP templates usually capture one
	 * with `start()` and `stop()`; this is for view engines with blocks of
	 * their own (D-502), and for HTML a template already has.
	 */
	public function setSection(string $name, Stringable|string $html): void
	{
		$this->context->setSection($name, (string) $html);
	}

	/**
	 * Starts capturing a section in a PHP template, with output buffering.
	 */
	public function start(string $name): void
	{
		$this->state->capturing[] = $name;
		ob_start();
	}

	/**
	 * Stops capturing the current section and stores it.
	 *
	 * @throws ViewException When no section is being captured.
	 */
	public function stop(): void
	{
		$name = array_pop($this->state->capturing) ?? throw new ViewException('stop() was called without start().');

		$this->context->setSection($name, (string) ob_get_clean());
	}

	/**
	 * Returns a section's content, or a default. Sections hold rendered
	 * HTML, so print them as they are: `<?= $template->section('content') ?>`.
	 */
	#[ReturnsHtml]
	public function section(string $name, string $default = ''): string
	{
		return $this->context->section($name) ?? $default;
	}

	/**
	 * Returns whether a section has been set.
	 */
	public function hasSection(string $name): bool
	{
		return $this->context->section($name) !== null;
	}

	/**
	 * Renders a partial (`partials/header`) with named data and returns it.
	 * Given a list, it renders the first that exists, so a template can
	 * offer a specific partial with a fallback:
	 * `<?= $template->include(["partials/summary-{$type}", 'partials/summary'], entry: $entry) ?>`.
	 *
	 * @param  string|list<string> $views
	 * @throws ViewException When none exists.
	 */
	#[ReturnsHtml]
	public function include(string|array $views, mixed ...$data): string
	{
		return $this->views->partial($views, self::named($data), $this->context);
	}

	/**
	 * Renders a partial like `include()`, or returns `''` when none of
	 * the views exists.
	 *
	 * @param  string|list<string> $views
	 * @throws ViewException
	 */
	#[ReturnsHtml]
	public function includeIf(string|array $views, mixed ...$data): string
	{
		return $this->views->exists($views) ? $this->include($views, ...$data) : '';
	}

	/**
	 * Renders a partial like `include()` when `$when` is truthy, or
	 * returns `''`.
	 *
	 * @param  string|list<string> $views
	 * @throws ViewException
	 */
	#[ReturnsHtml]
	public function includeWhen(mixed $when, string|array $views, mixed ...$data): string
	{
		return $when ? $this->include($views, ...$data) : '';
	}

	/**
	 * Renders a partial like `include()` unless `$unless` is truthy.
	 *
	 * @param  string|list<string> $views
	 * @throws ViewException
	 */
	#[ReturnsHtml]
	public function includeUnless(mixed $unless, string|array $views, mixed ...$data): string
	{
		return $unless ? '' : $this->include($views, ...$data);
	}

	/**
	 * Renders a partial once per item, passing the item as `$as` (and
	 * its position, from zero, as `$index`), with any other named data.
	 * With no items, it renders `$empty` instead, when given:
	 * `<?= $template->each('partials/summary', $entries, as: 'entry', empty: 'partials/none') ?>`.
	 *
	 * @param  string|list<string>       $views
	 * @param  iterable<mixed>           $items
	 * @param  string|list<string>|null  $empty
	 * @throws ViewException
	 */
	#[ReturnsHtml]
	public function each(string|array $views, iterable $items, string $as = 'item', string|array|null $empty = null, mixed ...$data): string
	{
		$data   = self::named($data);
		$output = '';
		$index  = 0;

		foreach ($items as $item) {
			$output .= $this->views->partial($views, [...$data, $as => $item, 'index' => $index++], $this->context);
		}

		return $index === 0 && $empty !== null ? $this->include($empty, ...$data) : $output;
	}

	/**
	 * Returns a component with named props, to print or to fill with
	 * slots first (D-025, D-532):
	 * `<?= $template->component('acme/card', entry: $entry)->slot('footer', $html) ?>`.
	 * `$component` is its full name (`{namespace}/{name}`, D-171). (It
	 * isn't called `$name`, so a component can have a `name` prop;
	 * `$component` is taken in component templates anyway.)
	 */
	#[ReturnsHtml]
	public function component(string $component, mixed ...$props): PendingComponent
	{
		return new PendingComponent($this->views, $this->context, $component, self::named($props));
	}

	/**
	 * Returns a component like `component()`, which renders `''` when
	 * the component doesn't exist (D-826), so a theme can offer a spot
	 * for one a plugin it suggests may add:
	 * `<?= $template->componentIf('acme/powered-by') ?>`. Its name must
	 * still be valid, and a component that exists but fails still
	 * throws.
	 */
	#[ReturnsHtml]
	public function componentIf(string $component, mixed ...$props): PendingComponent
	{
		return new PendingComponent($this->views, $this->context, $component, self::named($props), optional: true);
	}

	/**
	 * Returns whether a component exists (D-532, D-826): a class is
	 * registered for it, or the chain has its template.
	 */
	public function hasComponent(string $component): bool
	{
		return $this->views->hasComponent($component);
	}

	/**
	 * Returns a block with named props, to print or to give content
	 * first (D-532): what content would say, drawn by a template, as
	 * `<?= $template->block('callout', variant: 'tip')->content($html) ?>`.
	 * `$block` is a full name (`acme/tabs`) or a core block's
	 * short name (D-171). (It isn't called `$name`, so a block can
	 * have a `name` prop, as the menu does.)
	 */
	#[ReturnsHtml]
	public function block(string $block, mixed ...$props): PendingBlock
	{
		return new PendingBlock($this->views, $this->context, $block, self::named($props));
	}

	/**
	 * Returns a block like `block()`, which renders `''` when the block
	 * isn't registered (D-826): `<?= $template->blockIf('acme/share') ?>`.
	 * Its name must still be valid, and a block that's registered but
	 * fails still throws.
	 */
	#[ReturnsHtml]
	public function blockIf(string $block, mixed ...$props): PendingBlock
	{
		return new PendingBlock($this->views, $this->context, $block, self::named($props), optional: true);
	}

	/**
	 * Returns whether a block is registered (D-532, D-826). `$block` is
	 * a full name or a core block's short name.
	 */
	public function hasBlock(string $block): bool
	{
		return $this->views->hasBlock($block);
	}

	/**
	 * Returns an icon (D-187), to print: `<?= $template->icon('house') ?>`
	 * for decoration, or `<?= $template->icon('jtcom/github', 'GitHub') ?>`
	 * for one named by its label.
	 */
	#[ReturnsHtml]
	public function icon(string $name, string $label = ''): PendingBlock
	{
		return $this->block('icon', name: $name, label: $label);
	}

	/**
	 * Returns the menu a theme location shows (D-199), with the page's
	 * item marked current, or `null` when it shows none, for markup of
	 * the theme's own: the menu the site assigned it, else the theme's
	 * default items (D-676). The `menu` block prints one with the
	 * default markup: `<?= $template->block('menu', location: 'primary') ?>`.
	 *
	 * ```php
	 * <?php if ($menu = $template->menu('social')) : ?>
	 *     <ul>
	 *         <?php foreach ($menu->items as $item) : ?>
	 *             <li><a href="<?= url($item->url) ?>"><?= e($item->label) ?></a></li>
	 *         <?php endforeach ?>
	 *     </ul>
	 * <?php endif ?>
	 * ```
	 */
	public function menu(string $location): ?Menu
	{
		return $this->views->services->menus
			->forLocation($this->views->chain, $location, $this->context->locale)
			?->forPath($this->context->path, $this->views->services->app->origin());
	}

	/**
	 * Returns the page's `Head`.
	 */
	public function head(): Head
	{
		return $this->context->head;
	}

	/**
	 * Returns the end of the page's `<body>` (D-578): add scripts to it
	 * (`$template->foot()->script($url)`), and print it in the base
	 * layout just before `</body>` (D-577): `<?= $template->foot() ?>`.
	 * Registered scripts that say `footer` print there too, so a layout
	 * without it gets none.
	 */
	public function foot(): Foot
	{
		return $this->context->markup->foot;
	}

	/**
	 * Returns a fragment of HTML, rendering it only when the cache doesn't
	 * have it (D-152). It's kept per content version and active theme, so
	 * publishing or switching themes renders it again; with caching off
	 * (development), it always renders. The key names the fragment and
	 * anything it varies by:
	 *
	 *     <?= $template->cache("archives.{$by}", fn () => $template->component('acme/post-archives', by: $by)) ?>
	 *
	 * The returned HTML is kept with the assets the fragment's blocks
	 * and components asked for (D-572), which are asked for again
	 * whenever it's printed. Nothing else is, so a fragment shouldn't add
	 * to the head or the `<body>` classes itself.
	 *
	 * @param Closure(): (string|Stringable) $render
	 * @throws CacheException When the fragment store can't be built.
	 */
	#[ReturnsHtml]
	public function cache(string $key, Closure $render): string
	{
		$cache     = $this->views->services->cache;
		$collector = $this->views->services->collector;

		if ($cache === null) {
			return (string) $render();
		}

		// Kept as the HTML and its assets; the key's `assets` keeps a
		// fragment cached as HTML alone from being read as both.
		[$html, $assets] = $cache->remember(
			CacheNamespace::Fragments,
			$this->views->chain->active()->name . ' assets ' . $key,
			static fn (): array => $collector->collect(static fn (): string => (string) $render())
		);

		$collector->add(...$assets);

		return $html;
	}

	/**
	 * Asks for registered assets by handle (D-570), such as
	 * `core/player`, for the page this template is part of. They print
	 * in its head (or a script's footer), after what they require, each
	 * once. Asked for in a block's template, they're kept with the
	 * body it's in, as the block's own are (D-572).
	 */
	public function enqueue(string ...$handles): void
	{
		$this->views->services->collector->add(...$handles);
		$this->context->markup->enqueue(...$handles);
	}

	/**
	 * Escapes text and joins its last two words with a non-breaking space,
	 * so a title doesn't end with one word alone on its last line (a
	 * "runt"; the fix is known as "widont"). 1.x's `runt()` (D-153). Text
	 * of three words or fewer is only escaped. Print the result as is:
	 * `<?= $template->widont($title) ?>`.
	 */
	#[ReturnsHtml]
	public function widont(Stringable|string $text): string
	{
		$html  = e($text);
		$words = preg_split('/ +/', trim($html), -1, PREG_SPLIT_NO_EMPTY) ?: [];

		if (count($words) <= 3) {
			return $html;
		}

		$last = array_pop($words);

		return implode(' ', $words) . '&nbsp;' . $last;
	}

	/**
	 * Returns a named route's URL. On a page in another language (D-464),
	 * it's the language's route when there is one (`es:home` for `home`,
	 * `/es`), so a theme's links to the homepage, collections, and date
	 * archives stay in the page's language; a route without one (a feed)
	 * is as named.
	 *
	 * @param  array<string, BackedEnum|Stringable|scalar|null> $params
	 * @throws UrlGenerationException
	 */
	public function route(string $name, array $params = [], bool $absolute = false): string
	{
		$router   = $this->views->services->router;
		$language = $this->context->language;

		if ($language !== '' && $router->has("{$language}:{$name}")) {
			return $router->to("{$language}:{$name}", $params, $absolute);
		}

		return $router->to($name, $params, $absolute);
	}

	/**
	 * Formats a date in the page's locale and timezone, with the site's
	 * date format (`app.dateFormat`, D-445) or the one given: `full`,
	 * `long`, `medium`, or `short` (or a `DateStyle`, D-447), or else an
	 * ICU pattern (`'MMMM y'`).
	 */
	public function date(DateTimeInterface $date, DateStyle|string|null $format = null): string
	{
		return DateFormat::format($date, $this->locale() ?? $this->views->translator->locale(), $this->views->services->app->timezone, $format ?? $this->views->services->app->dateFormat);
	}

	/**
	 * Formats a time in the page's locale and timezone, with the site's
	 * time format (`app.timeFormat`, D-445) or the one given, as `date()`
	 * takes them.
	 */
	public function time(DateTimeInterface $date, DateStyle|string|null $format = null): string
	{
		return DateFormat::format($date, $this->locale() ?? $this->views->translator->locale(), $this->views->services->app->timezone, null, $format ?? $this->views->services->app->timeFormat);
	}

	/**
	 * Formats a date and its time, joined as the site's language joins
	 * them (`October 4, 2026 at 2:30 PM`), with the site's formats or the
	 * ones given (D-445).
	 */
	public function datetime(DateTimeInterface $date, DateStyle|string|null $dateFormat = null, DateStyle|string|null $timeFormat = null): string
	{
		$app = $this->views->services->app;

		return DateFormat::format($date, $this->locale() ?? $this->views->translator->locale(), $app->timezone, $dateFormat ?? $app->dateFormat, $timeFormat ?? $app->timeFormat);
	}

	/**
	 * Returns the `<body>` classes as an attribute value (escape it with
	 * `attr()`).
	 */
	public function bodyClass(): string
	{
		return implode(' ', $this->context->classes());
	}

	/**
	 * Returns the page's locale (its entry's, so a translation's text and
	 * dates are in its language; D-455), or `null` for the site's.
	 */
	private function locale(): ?string
	{
		return $this->context->locale === '' ? null : $this->context->locale;
	}

	/**
	 * Keeps only named values, since data becomes template variables.
	 *
	 * @param  array<array-key, mixed> $data
	 * @return array<string, mixed>
	 * @throws ViewException When a value isn't named.
	 */
	private static function named(array $data): array
	{
		foreach (array_keys($data) as $key) {
			if (! is_string($key)) {
				throw new ViewException('Pass view data by name, such as include(\'partials/card\', entry: $entry).');
			}
		}

		/** @var array<string, mixed> $data */
		return $data;
	}
}
