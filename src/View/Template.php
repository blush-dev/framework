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
use Blush\Content\Entry\Entry;
use Blush\Content\Relation\Relation;
use Blush\Content\Type\ContentType;
use Blush\Data\InvalidData;
use Blush\Menu\Menu;
use Blush\Menu\MenuException;
use Blush\Region\RegionException;
use Blush\Routing\UrlGenerationException;
use Blush\Theme\ThemeException;
use Blush\Component\PendingComponent;
use Blush\Directive\PendingDirective;

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
	 * The layout this template asked for, and its data.
	 *
	 * @var ?array{string, array<string, mixed>}
	 */
	private ?array $layout = null;

	/**
	 * Sections being captured, innermost last.
	 *
	 * @var list<string>
	 */
	private array $capturing = [];

	public function __construct(
		private readonly Views $views,
		private readonly ViewContext $context
	) {}

	/**
	 * Wraps this template in a layout (`layouts/{name}`, so `shells/wide`
	 * is `layouts/shells/wide`), with extra data for it. The template's
	 * output becomes the layout's `content` section.
	 */
	public function layout(string $name, mixed ...$data): void
	{
		$this->layout = ["layouts/{$name}", self::named($data)];
	}

	/**
	 * Returns the layout this template asked for, and its data. Used by
	 * `Views` after the template runs.
	 *
	 * @internal
	 * @return ?array{string, array<string, mixed>}
	 */
	public function requestedLayout(): ?array
	{
		return $this->layout;
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
		$this->capturing[] = $name;
		ob_start();
	}

	/**
	 * Stops capturing the current section and stores it.
	 *
	 * @throws ViewException When no section is being captured.
	 */
	public function stop(): void
	{
		$name = array_pop($this->capturing) ?? throw new ViewException('stop() was called without start().');

		$this->context->setSection($name, (string) ob_get_clean());
	}

	/**
	 * Returns the sections still being captured, innermost last. Used by
	 * `Views` to catch a missing `stop()`.
	 *
	 * @internal
	 * @return list<string>
	 */
	public function openSections(): array
	{
		return $this->capturing;
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
	 * Returns a directive with named props, to print or to give content
	 * first (D-532): what content would say, drawn by a template, as
	 * `<?= $template->directive('callout', variant: 'tip')->content($html) ?>`.
	 * `$directive` is a full name (`acme/tabs`) or a core directive's
	 * short name (D-171). (It isn't called `$name`, so a directive can
	 * have a `name` prop, as the menu does.)
	 */
	#[ReturnsHtml]
	public function directive(string $directive, mixed ...$props): PendingDirective
	{
		return new PendingDirective($this->views, $this->context, $directive, self::named($props));
	}

	/**
	 * Returns an icon (D-187), to print: `<?= $template->icon('house') ?>`
	 * for decoration, or `<?= $template->icon('jtcom/github', 'GitHub') ?>`
	 * for one named by its label.
	 */
	#[ReturnsHtml]
	public function icon(string $name, string $label = ''): PendingDirective
	{
		return $this->directive('icon', name: $name, label: $label);
	}

	/**
	 * Returns the menu a theme location shows (D-199), with the page's
	 * item marked current, or `null` when it shows none, for markup of
	 * the theme's own. The `menu` directive prints one with the default
	 * markup: `<?= $template->directive('menu', name: 'primary') ?>`.
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
	 *
	 * @throws MenuException When the theme's location declaration is invalid.
	 * @throws InvalidData When a menu file can't be read.
	 */
	public function menu(string $location): ?Menu
	{
		return $this->views->services->menus
			->forLocation($this->views->chain, $location, $this->context->locale)
			?->forPath($this->context->path, $this->views->services->app->origin());
	}

	/**
	 * Returns the HTML of the region a theme location shows (D-201), or
	 * `''`: its items rendered in order. Wrap it in the theme's own markup,
	 * guarded by `hasRegion()`:
	 *
	 * ```php
	 * <?php if ($template->hasRegion('sidebar')) : ?>
	 *     <aside class="sidebar"><?= $template->region('sidebar') ?></aside>
	 * <?php endif ?>
	 * ```
	 *
	 * @throws RegionException When the theme's location declaration is invalid.
	 * @throws InvalidData When a region file can't be read.
	 */
	#[ReturnsHtml]
	public function region(string $location): string
	{
		return $this->views->services->regions->render($this->views, $this->context, $location);
	}

	/**
	 * Returns whether a theme location shows a region with any items.
	 *
	 * @throws RegionException
	 * @throws InvalidData
	 */
	public function hasRegion(string $location): bool
	{
		return $this->views->services->regions->has($this->views->chain, $location);
	}

	/**
	 * Returns a theme setting's value (from `user/data/theme.json`, or
	 * the theme's default), or `$default` when no theme in the chain
	 * declares it.
	 */
	public function setting(string $name, mixed $default = null): mixed
	{
		return $this->views->settings->get($name, $default);
	}

	/**
	 * Returns a site setting's value (D-343): one a field set adds to the
	 * admin's Settings screens, saved in `user/data/settings.json`, or its
	 * field's default, or `$default` when it has neither.
	 */
	public function site(string $name, mixed $default = null): mixed
	{
		return $this->views->services->site->get($name, $default);
	}

	/**
	 * Translates a message from the theme's catalogs with named
	 * parameters: `$template->t('reading_time', minutes: 5)` (D-028).
	 * It's text, so print it with `e()` or `attr()`.
	 *
	 * A parameter marked with `raw()` is HTML (D-559): the message comes
	 * back as HTML, with that parameter as given and the message's own
	 * text and every other parameter escaped, so
	 * `e($template->t('by', names: raw($links)))` keeps the links. `attr()`
	 * still escapes it all.
	 *
	 * @throws InvalidData When a catalog can't be parsed.
	 */
	public function t(string $key, mixed ...$params): string|SafeHtml
	{
		$params = self::named($params);
		$html   = [];

		// Each marked parameter is held by a private-use token while the
		// message is formatted and escaped, then put back as given.
		foreach ($params as $name => $value) {
			if ($value instanceof SafeHtml) {
				$token         = "\u{E000}" . count($html) . "\u{E001}";
				$html[$token]  = (string) $value;
				$params[$name] = $token;
			}
		}

		$message = $this->views->messages->translate($key, $params, $this->locale());

		return $html === [] ? $message : new TrustedHtml(strtr(Escaper::html($message), $html));
	}

	/**
	 * Translates a group of messages from the theme's catalogs (the keys
	 * under `$key`), keyed by name, such as the default footer's
	 * `powered_by` lines (D-450). A group comes whole from one locale.
	 * Empty when no locale has it.
	 *
	 * @return array<string, string>
	 * @throws InvalidData When a catalog can't be parsed.
	 */
	public function tGroup(string $key, mixed ...$params): array
	{
		return $this->views->messages->group($key, self::named($params), $this->locale());
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
	 * Returns a theme asset's URL (from a build manifest, or versioned by
	 * mtime), resolved through the theme chain, or `''` when no theme
	 * has it.
	 *
	 * @throws ThemeException When a build manifest is invalid.
	 */
	public function asset(string $path): string
	{
		return $this->views->assets->url($path) ?? '';
	}

	/**
	 * Returns a theme asset's contents, resolved through the theme chain,
	 * or `''` when no theme has it: `<?= raw($template->inline('svg/github.svg')) ?>`
	 * (D-151). Only servable assets can be read, never views or PHP.
	 */
	public function inline(string $path): string
	{
		$found = $this->views->chain->asset($path);

		return $found === null ? '' : (string) file_get_contents($found[1]);
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
	 * The returned HTML is kept with the assets the fragment's directives
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
	 * `blush/player`, for the page this template is part of. They print
	 * in its head (or a script's footer), after what they require, each
	 * once. Asked for in a directive's template, they're kept with the
	 * body it's in, as the directive's own are (D-572).
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
	public function widont(string $text): string
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
	 * Returns an entry's URL path, or `''` when it has none.
	 */
	public function permalink(Entry $entry): string
	{
		return $this->views->services->urls->entry($entry) ?? '';
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
	 * Returns the term entries an entry has of a term type (one a
	 * classify relation files entries under, D-593), in the order front
	 * matter lists them. Terms that aren't published are left out.
	 * Profiles aren't terms here; use `people()`.
	 *
	 * @return list<Entry>
	 */
	public function terms(Entry $entry, string $taxonomy): array
	{
		if ($this->views->services->types->classification($taxonomy) === null) {
			return [];
		}

		$terms = [];

		foreach ($entry->terms($taxonomy) as $slug) {
			$term = $this->views->services->content->term($taxonomy, $slug, $entry->language === '' ? null : $entry->language);

			if ($term !== null && $term->isPublished() && $term->isRoutable()) {
				$terms[] = $term;
			}
		}

		return $terms;
	}

	/**
	 * Returns the entries an entry links to through one of its type's
	 * relations (D-585, D-596), such as a movie's `actors`, in the order
	 * front matter lists them, each in the entry's language when it has a
	 * translation in it. Only published entries come back. A symmetric
	 * relation answers from both ends (related posts include the ones
	 * naming this one), and a translation's links follow the relation's
	 * `translations` rule.
	 *
	 * @return list<Entry>
	 */
	public function related(Entry $entry, string $relation): array
	{
		return $this->views->services->relations->related($entry, $relation);
	}

	/**
	 * Returns the published entries linking to an entry through a
	 * relation, newest published first: by its name (`actors`), or by
	 * its key on one source type (`movie.actors`). It's the list a
	 * relation's reverse side shows when it has no page of its own
	 * (`inverse.archive: false`).
	 *
	 * @return list<Entry>
	 */
	public function referencedBy(Entry $entry, string $relation): array
	{
		return $this->views->services->relations->referencedBy($entry, $relation);
	}

	/**
	 * Returns the people an entry's byline credits (D-602): its type's
	 * byline relation, such as a post's `authors`, in order, published
	 * profiles only.
	 *
	 * @return list<Entry>
	 */
	public function byline(Entry $entry): array
	{
		$byline = $this->views->services->types->byline($entry->type->name);

		return $byline === null ? [] : $this->related($entry, $byline->name);
	}

	/**
	 * Returns the first person an entry's byline credits, such as a
	 * post's author, or `null` when it credits no one.
	 *
	 * ```php
	 * <?php if ($author = $template->author($entry)) : ?>
	 *     <?= $template->avatar($author) ?> <?= e($author->title) ?>
	 * <?php endif ?>
	 * ```
	 */
	public function author(Entry $entry): ?Entry
	{
		return array_first($this->byline($entry));
	}

	/**
	 * Returns the people an entry credits, by credit relation (D-602): its
	 * byline first, then the type's others by name, each with the people
	 * it credits, leaving out those that credit no one. A relation's
	 * `label` and `singular` name it ("Photographers", "Photographer").
	 *
	 * ```php
	 * <?php foreach ($template->credits($entry) as $credit) : ?>
	 *     <?= e($credit['relation']->label) ?>:
	 *     <?php foreach ($credit['people'] as $person) : ?> … <?php endforeach ?>
	 * <?php endforeach ?>
	 * ```
	 *
	 * @return list<array{relation: Relation, byline: bool, people: list<Entry>}>
	 */
	public function credits(Entry $entry): array
	{
		$types   = $this->views->services->types;
		$byline  = $types->byline($entry->type->name);
		$credits = $types->credits($entry->type->name);

		ksort($credits);

		if ($byline !== null) {
			$credits = [$byline->name => $byline, ...$credits];
		}

		$found = [];

		foreach ($credits as $relation) {
			$people = $this->related($entry, $relation->name);

			if ($people !== []) {
				$found[] = ['relation' => $relation, 'byline' => $relation === $byline, 'people' => $people];
			}
		}

		return $found;
	}

	/**
	 * Returns a published profile by its slug (D-351), or `null` when
	 * there's none, or the site has no profiles type.
	 */
	public function profile(string $slug): ?Entry
	{
		$profiles = $this->views->services->types->profiles();
		$profile  = $profiles === null ? null : $this->views->services->content->term($profiles->name, $slug);

		return $profile !== null && $profile->isPublished() && $profile->isRoutable() ? $profile : null;
	}

	/**
	 * Returns a profile's avatar (D-536), to print: a square box with up
	 * to two initials of its name, as an inline SVG the theme styles.
	 * Without a label, it's hidden from assistive technology, for when
	 * the name is printed beside it; with one, it's an image named by it.
	 *
	 * ```php
	 * <?= $template->avatar($profile, 64) ?>
	 * <?= $template->avatar($profile, label: $profile->title) ?>
	 * ```
	 */
	#[ReturnsHtml]
	public function avatar(Entry $profile, int $size = 48, string $label = ''): string
	{
		return new Avatar($profile->title, $size)->html($label);
	}

	/**
	 * Returns the URL path of a relation archive under a type (D-596,
	 * D-602): one target's, such as `/recipes/cooks/jane` or
	 * `/movies/directors/penny`, or with no target, the list of what the
	 * relation links to (`/recipes/cooks`). `$relation` is the relation's
	 * name, or `null` for the type's byline. `''` when there's no such
	 * archive; a byline's link can fall back to the profile's own page:
	 *
	 * ```php
	 * <?= url($template->archiveUrl($entry->type, null, $person) ?: $template->permalink($person)) ?>
	 * ```
	 */
	public function archiveUrl(ContentType|string $type, ?string $relation = null, Entry|string|null $target = null): string
	{
		$services = $this->views->services;
		$type     = is_string($type) ? $services->types->find($type) : $type;
		$found    = $type === null ? null : ($relation === null ? $services->types->byline($type->name) : $services->types->relationArchives($type)[$relation] ?? null);

		if ($type === null || $found === null) {
			return '';
		}

		$slug = $target instanceof Entry ? $target->slug : $target;

		return ($slug === null ? $services->urls->relatedList($type, $found) : $services->urls->related($type, $found, $slug)) ?? '';
	}

	/**
	 * Returns an entry's parent: a page's (from its folder) or a
	 * hierarchical collection's entry's (its `parent`). `null` when there's
	 * none, or it isn't published.
	 */
	public function parent(Entry $entry): ?Entry
	{
		$parent = $this->views->services->content->parent($entry);

		return $parent !== null && $parent->isPublished() && $parent->isRoutable() ? $parent : null;
	}

	/**
	 * Returns an entry's ancestors, from the top down, such as for
	 * breadcrumbs. The chain stops at a parent that's missing or not
	 * published.
	 *
	 * @return list<Entry>
	 */
	public function ancestors(Entry $entry): array
	{
		$ancestors = [];
		$seen      = [$entry->id ?? $entry->path => true];

		while (($entry = $this->parent($entry)) !== null && ! isset($seen[$entry->id ?? $entry->path])) {
			$seen[$entry->id ?? $entry->path] = true;
			array_unshift($ancestors, $entry);
		}

		return $ancestors;
	}

	/**
	 * Returns the published entries whose parent is this one, by title:
	 * a page's subpages, or a term's child terms.
	 *
	 * @return list<Entry>
	 */
	public function children(Entry $entry): array
	{
		return array_values(array_filter(
			$this->views->services->content->children($entry),
			static fn (Entry $child): bool => $child->isPublished() && $child->isRoutable()
		));
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
