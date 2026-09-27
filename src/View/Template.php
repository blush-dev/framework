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
use IntlDateFormatter;
use Stringable;
use Blush\Cache\CacheException;
use Blush\Cache\CacheNamespace;
use Blush\Content\Entry\Entry;
use Blush\Data\InvalidData;
use Blush\Routing\UrlGenerationException;
use Blush\Theme\ThemeException;
use Blush\View\Component\PendingComponent;

/**
 * `$template` inside a template file: the small API templates use to build
 * pages (see `theming.md`). A template file runs in an isolated scope with
 * its data as variables, and can reach only this class's public methods
 * (D-158).
 *
 * ```php
 * <?php $template->layout('base') ?>
 *
 * <article class="entry">
 *     <h1><?= e($entry->title) ?></h1>
 *     <?= raw($entry->body()) ?>
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
	 * Wraps this template in a layout (`layouts/{name}`, unless the name
	 * has a folder), with extra data for it. The template's output
	 * becomes the layout's `content` section.
	 */
	public function layout(string $name, mixed ...$data): void
	{
		$this->layout = [str_contains($name, '/') ? $name : "layouts/{$name}", self::named($data)];
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
	 * Starts capturing a section.
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
	 * Renders a partial (`parts/header`) with named data and returns it.
	 * Given a list, it renders the first that exists, so a template can
	 * offer a specific partial with a fallback:
	 * `<?= $template->include(["parts/summary-{$type}", 'parts/summary'], entry: $entry) ?>`.
	 *
	 * @param  string|list<string> $views
	 * @throws ViewException When none exists.
	 */
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
	public function includeUnless(mixed $unless, string|array $views, mixed ...$data): string
	{
		return $unless ? '' : $this->include($views, ...$data);
	}

	/**
	 * Renders a partial once per item, passing the item as `$as` (and
	 * its position, from zero, as `$index`), with any other named data.
	 * With no items, it renders `$empty` instead, when given:
	 * `<?= $template->each('parts/summary', $entries, as: 'entry', empty: 'parts/none') ?>`.
	 *
	 * @param  string|list<string>       $views
	 * @param  iterable<mixed>           $items
	 * @param  string|list<string>|null  $empty
	 * @throws ViewException
	 */
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
	 * slots first (D-025):
	 * `<?= $template->component('callout', tone: 'info')->content($html) ?>`.
	 */
	public function component(string $key, mixed ...$props): PendingComponent
	{
		return new PendingComponent($this->views, $this->context, $key, self::named($props));
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
	 * Translates a message from the theme's catalogs with named
	 * parameters: `$template->t('reading_time', minutes: 5)` (D-028).
	 *
	 * @throws InvalidData When a catalog can't be parsed.
	 */
	public function t(string $key, mixed ...$params): string
	{
		return $this->views->translator->translate($key, self::named($params), 'theme');
	}

	/**
	 * Returns the page's `Head`.
	 */
	public function head(): Head
	{
		return $this->context->head;
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
	 *     <?= $template->cache("archives.{$by}", fn () => $template->component('post-archives', by: $by)) ?>
	 *
	 * Only the returned HTML is kept, so a fragment shouldn't add to the
	 * head or the `<body>` classes.
	 *
	 * @param Closure(): (string|Stringable) $render
	 * @throws CacheException When the fragment store can't be built.
	 */
	public function cache(string $key, Closure $render): string
	{
		$html = static fn (): string => (string) $render();

		return $this->views->services->cache?->remember(
			CacheNamespace::Fragments,
			$this->views->chain->active()->slug . '.' . $key,
			$html
		) ?? $html();
	}

	/**
	 * Escapes text and joins its last two words with a non-breaking space,
	 * so a title doesn't end with one word alone on its last line (a
	 * "runt"; the fix is known as "widont"). 1.x's `runt()` (D-153). Text
	 * of three words or fewer is only escaped. Print the result as is:
	 * `<?= $template->widont($title) ?>`.
	 */
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
	 * Returns a named route's URL.
	 *
	 * @param  array<string, BackedEnum|Stringable|scalar|null> $params
	 * @throws UrlGenerationException
	 */
	public function route(string $name, array $params = [], bool $absolute = false): string
	{
		return $this->views->services->router->to($name, $params, $absolute);
	}

	/**
	 * Returns the term entries an entry has in a taxonomy, in the order
	 * front matter lists them. Terms that aren't published are left out.
	 *
	 * @return list<Entry>
	 */
	public function terms(Entry $entry, string $taxonomy): array
	{
		$terms = [];

		foreach ($entry->terms($taxonomy) as $slug) {
			$term = $this->views->services->content->term($taxonomy, $slug);

			if ($term !== null && $term->isPublished() && $term->isRoutable()) {
				$terms[] = $term;
			}
		}

		return $terms;
	}

	/**
	 * Formats a date in the site's locale and timezone: `full`, `long`,
	 * `medium`, or `short`, or else an ICU pattern (`'MMMM y'`).
	 */
	public function date(DateTimeInterface $date, string $format = 'long'): string
	{
		$style     = ['full' => IntlDateFormatter::FULL, 'long' => IntlDateFormatter::LONG, 'medium' => IntlDateFormatter::MEDIUM, 'short' => IntlDateFormatter::SHORT][$format] ?? null;
		$formatter = new IntlDateFormatter(
			$this->views->translator->locale(),
			$style ?? IntlDateFormatter::NONE,
			IntlDateFormatter::NONE,
			$this->views->services->app->timezone,
			null,
			$style === null ? $format : null
		);

		$formatted = $formatter->format($date);

		return $formatted === false ? $date->format('Y-m-d') : $formatted;
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
				throw new ViewException('Pass view data by name, such as include(\'parts/card\', entry: $entry).');
			}
		}

		/** @var array<string, mixed> $data */
		return $data;
	}
}
