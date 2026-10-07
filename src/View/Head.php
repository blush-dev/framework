<?php

/**
 * Page head.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View;

use Override;
use Blush\Theme\ThemeException;

/**
 * What goes in a page's `<head>` (D-578): the title, meta tags
 * (including OpenGraph properties), links (canonical, alternates,
 * pagination), resource hints, stylesheets, and scripts. Templates and
 * the renderer add to it while the page renders, and the base layout
 * prints it once, as everything between `<head>` and `</head>`, with
 * `<?= $template->head() ?>`. Its tags are kept in the page's
 * `PageMarkup`, which it shares with the `Foot`.
 *
 * Each tag is keyed, so adding it twice keeps one copy (the later value)
 * in the place it was first added. The head prints `<meta charset>`
 * first (Blush is UTF-8 throughout, and the encoding has to come before
 * any text), then the title, then the tags grouped by kind (D-472):
 * `<meta name>` tags, `<meta property>` tags, links, resource hints
 * (`preload`, `preconnect`, …), styles, data for scripts, scripts, then
 * inline scripts (D-579, D-580); data and inline code tied to an asset
 * print beside its scripts instead. Each group keeps the
 * order its tags were added in, and stylesheets and inline styles share a
 * group so the cascade stays as added. Every line is indented by one tab,
 * and an inline style's CSS by two.
 *
 * Root-relative `href` and `src` values (`/feed`, a theme asset) print as
 * full URLs on the site's origin, so the head never has relative URLs.
 * Keys keep the value as given, so `remove('style:' . $url)` still works.
 */
final class Head implements SafeHtml
{
	/**
	 * The page's own title, without the site name.
	 */
	private string $title = '';

	/**
	 * Link `rel` values printed as resource hints, after the other links
	 * and before the styles they often serve.
	 */
	private const array HINTS = ['dns-prefetch', 'modulepreload', 'preconnect', 'prefetch', 'preload'];

	/**
	 * Built by its `PageMarkup` (`$markup->head`).
	 *
	 * @internal
	 */
	public function __construct(
		private readonly PageMarkup $markup,
		private readonly string $siteName = '',
		private readonly string $separator = ' | '
	) {}

	/**
	 * Sets the page's title.
	 */
	public function title(string $title): self
	{
		$this->title = trim($title);

		return $this;
	}

	/**
	 * Returns the page's title, without the site name.
	 */
	public function pageTitle(): string
	{
		return $this->title;
	}

	/**
	 * Returns the `<title>` text: the page title and the site name, or
	 * just one of them.
	 */
	public function documentTitle(): string
	{
		return match (true) {
			$this->title === ''    => $this->siteName,
			$this->siteName === '' => $this->title,
			default                => $this->title . $this->separator . $this->siteName
		};
	}

	/**
	 * Adds a `<meta name>` tag.
	 */
	public function meta(string $name, string $content): self
	{
		return $this->add("meta:{$name}", 'meta', ['name' => $name, 'content' => $content]);
	}

	/**
	 * Adds a `<meta property>` tag, as OpenGraph uses.
	 */
	public function property(string $property, string $content): self
	{
		return $this->add("property:{$property}", 'meta', ['property' => $property, 'content' => $content]);
	}

	/**
	 * Adds another `<meta property>` tag for a property that may repeat,
	 * such as `article:author`. These are keyed by property and content
	 * (`property:{name}:{content}`), as links are, so each value is one
	 * tag.
	 */
	public function addProperty(string $property, string $content): self
	{
		return $this->add("property:{$property}:{$content}", 'meta', ['property' => $property, 'content' => $content]);
	}

	/**
	 * Adds a `<link>`. Links are keyed by `rel`, `href`, and any
	 * `hreflang`, so a page can have several alternates, and one URL can
	 * be both a language's alternate and `x-default` (D-461).
	 *
	 * @param array<string, string|bool> $attributes
	 */
	public function link(string $rel, string $href, array $attributes = []): self
	{
		$language = $attributes['hreflang'] ?? null;

		return $this->add("link:{$rel}:{$href}" . (is_string($language) ? ":{$language}" : ''), 'link', ['rel' => $rel, 'href' => $href, ...$attributes]);
	}

	/**
	 * Preloads a file the page will need early, such as a font its
	 * stylesheet uses, once per URL. What it is comes from its extension
	 * (`as`, and a font's `type`; fonts are fetched `crossorigin`, as
	 * browsers require); attributes given win:
	 * `preload($template->asset('fonts/body.woff2'))`.
	 *
	 * @param array<string, string|bool> $attributes
	 */
	public function preload(string $href, array $attributes = []): self
	{
		$extension = strtolower(pathinfo(strtok($href, '?#') ?: '', PATHINFO_EXTENSION));
		$fonts     = ['woff2' => 'font/woff2', 'woff' => 'font/woff', 'ttf' => 'font/ttf', 'otf' => 'font/otf'];

		$inferred = match (true) {
			isset($fonts[$extension])                                                    => ['as' => 'font', 'type' => $fonts[$extension], 'crossorigin' => true],
			$extension === 'css'                                                         => ['as' => 'style'],
			in_array($extension, ['js', 'mjs'], true)                                    => ['as' => 'script'],
			in_array($extension, ['avif', 'gif', 'jpeg', 'jpg', 'png', 'svg', 'webp'], true) => ['as' => 'image'],
			default                                                                      => []
		};

		return $this->link('preload', $href, [...$inferred, ...$attributes]);
	}

	/**
	 * Sets the canonical URL. A page has one, so a later call replaces it.
	 */
	public function canonical(string $url): self
	{
		return $this->add('link:canonical', 'link', ['rel' => 'canonical', 'href' => $url]);
	}

	/**
	 * Adds a stylesheet, once per URL.
	 *
	 * @param array<string, string|bool> $attributes
	 */
	public function style(string $href, array $attributes = []): self
	{
		return $this->add("style:{$href}", 'link', ['rel' => 'stylesheet', 'href' => $href, ...$attributes]);
	}

	/**
	 * Adds a script, once per URL. Scripts are deferred unless the
	 * attributes say otherwise (`['defer' => false]`, or `type: module`).
	 * For one at the end of the page, use the `Foot`.
	 *
	 * @param array<string, string|bool> $attributes
	 */
	public function script(string $src, array $attributes = []): self
	{
		return $this->add("script:{$src}", 'script', PageMarkup::scriptAttributes($src, $attributes));
	}

	/**
	 * Adds an inline `<style>` block, once per id. The CSS must be
	 * trusted (it isn't escaped); `</style` is refused.
	 *
	 * @throws ViewException
	 */
	public function inlineStyle(string $id, string $css): self
	{
		if (str_contains(strtolower($css), '</style')) {
			throw new ViewException(sprintf('Inline style "%s" can\'t contain "</style".', $id));
		}

		return $this->add("inline-style:{$id}", 'style', ['id' => $id], $css);
	}

	/**
	 * Adds an inline `<script>`, once per id, such as a few lines that
	 * must run before the page paints (a saved color scheme). It runs
	 * where it stands, unlike the theme's deferred scripts. The
	 * JavaScript must be trusted (it isn't escaped); `</script` is
	 * refused.
	 *
	 * Code that needs a registered asset names it (`after:
	 * 'acme/gallery'`, D-580): the asset is asked for, and the code
	 * prints just after its last script, wherever that prints, as a
	 * module when that script is deferred, so it runs after it.
	 *
	 * @throws ViewException
	 */
	public function inlineScript(string $id, string $js, ?string $after = null): self
	{
		$this->markup->addInlineScript(Placement::Head, $id, $js, $after);

		return $this;
	}

	/**
	 * Adds data for scripts to read, once per id (D-580): the value as
	 * JSON in `<script type="application/json" id="{id}">`, which never
	 * runs. Data a registered asset reads names it (`for:
	 * 'acme/gallery'`): the asset is asked for, and the data prints just
	 * before its first script.
	 *
	 * @throws ViewException When the value can't be JSON.
	 */
	public function data(string $id, mixed $value, ?string $for = null): self
	{
		$this->markup->addData(Placement::Head, $id, $value, $for);

		return $this;
	}

	/**
	 * Asks for registered assets by handle (D-570); see
	 * `PageMarkup::enqueue()`.
	 */
	public function enqueue(string ...$handles): self
	{
		$this->markup->enqueue(...$handles);

		return $this;
	}

	/**
	 * Takes back assets asked for; see `PageMarkup::dequeue()`.
	 */
	public function dequeue(string ...$handles): self
	{
		$this->markup->dequeue(...$handles);

		return $this;
	}

	/**
	 * Returns whether an asset has been asked for.
	 */
	public function isEnqueued(string $handle): bool
	{
		return $this->markup->isEnqueued($handle);
	}

	/**
	 * Returns the asset handles asked for, in the order asked.
	 *
	 * @return list<string>
	 */
	public function enqueued(): array
	{
		return $this->markup->enqueued();
	}

	/**
	 * Returns whether a tag has been added, in the head or the foot, by
	 * its key; see `PageMarkup::has()`.
	 */
	public function has(string $key): bool
	{
		return $this->markup->has($key);
	}

	/**
	 * Removes a tag by its key, such as a theme stylesheet a standalone
	 * page doesn't want:
	 * `$template->head()->remove('style:' . $template->asset('style.css'))` (D-154).
	 */
	public function remove(string $key): self
	{
		$this->markup->remove($key);

		return $this;
	}

	/**
	 * Renders the charset, the title, and the head's tags, grouped by
	 * kind.
	 *
	 * @throws ThemeException When an asset's theme has an invalid build manifest.
	 */
	public function render(): string
	{
		$lines = ['<meta charset="utf-8">', sprintf('<title>%s</title>', Escaper::html($this->documentTitle()))];
		$tags  = $this->markup->tags(Placement::Head);

		usort($tags, fn(array $a, array $b): int => $this->group($a[1], $a[2]) <=> $this->group($b[1], $b[2]));

		foreach ($this->markup->arrange($tags) as [, $element, $attributes, $content]) {
			$lines[] = $this->markup->tag($element, $attributes, $content);
		}

		return "\t" . implode("\n\t", $lines);
	}

	/**
	 * Prints the head, or its placeholder while the page is held.
	 *
	 * @inheritDoc
	 */
	#[Override]
	public function __toString(): string
	{
		return $this->markup->print(Placement::Head);
	}

	/**
	 * Adds or replaces a tag in the head.
	 *
	 * @param array<string, string|bool> $attributes
	 */
	private function add(string $key, string $element, array $attributes, string $content = ''): self
	{
		$this->markup->add(Placement::Head, $key, $element, $attributes, $content);

		return $this;
	}

	/**
	 * Returns the group a tag prints in (see the class summary). `usort()`
	 * is stable, so tags in one group keep the order they were added in.
	 *
	 * @param array<string, string|bool> $attributes
	 */
	private function group(string $element, array $attributes): int
	{
		$rel = $attributes['rel'] ?? '';

		return match (true) {
			$element === 'meta'                                      => isset($attributes['property']) ? 1 : 0,
			$element === 'style', $rel === 'stylesheet'              => 4,
			$element === 'link' && in_array($rel, self::HINTS, true) => 3,
			$element === 'link'                                      => 2,
			($attributes['type'] ?? null) === 'application/json'    => 5,
			! isset($attributes['src'])                              => 7,
			default                                                  => 6
		};
	}
}
