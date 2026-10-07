<?php

/**
 * Document head.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View;

use Override;
use Blush\Asset\Assets;
use Blush\Theme\ThemeException;

/**
 * Collects what goes in a page's `<head>`: the title, meta tags
 * (including OpenGraph properties), links (canonical, alternates,
 * pagination), stylesheets, and scripts. Templates and the renderer add
 * to it while the page renders, and the base layout prints it once,
 * as everything between `<head>` and `</head>`, with
 * `<?= $template->head() ?>`. Since layouts render after the templates they
 * wrap, anything a template adds is in place by then.
 *
 * Each item is keyed, so adding it twice keeps one copy (the later value)
 * in the place it was first added. The head prints `<meta charset>`
 * first (Blush is UTF-8 throughout, and the encoding has to come before
 * any text), then the title, then the tags grouped by kind (D-472):
 * `<meta name>` tags, `<meta property>` tags, links, resource hints
 * (`preload`, `preconnect`, …), styles, then scripts. Each group keeps the
 * order its tags were added in, and stylesheets and inline styles share a
 * group so the cascade stays as added. Every line is indented by one tab,
 * and an inline style's CSS by two.
 *
 * Root-relative `href` and `src` values (`/feed`, a theme asset) print as
 * full URLs on the site's origin, so the head never has relative URLs.
 * Keys keep the value as given, so `remove('style:' . $url)` still works.
 *
 * Registered assets are asked for by handle (`enqueue('blush/player')`,
 * D-570), and their files are added when the head prints. A script can
 * print in the footer instead, just before `</body>` (`foot()`).
 *
 * While a page renders, its head is held (`hold()`): printing it leaves
 * a placeholder, and `fill()` puts the head there once the whole page
 * has rendered, with the footer before `</body>`. So whatever renders
 * after the layout prints the head (the site footer, a component in it)
 * can still add to it.
 */
final class Head implements SafeHtml
{
	/**
	 * The page's own title, without the site name.
	 */
	private string $title = '';

	/**
	 * Tags by key, in the order they were added: the element, its
	 * attributes, its content (inline styles and scripts only), and
	 * whether it prints in the footer.
	 *
	 * @var array<string, array{string, array<string, string|bool>, string, bool}>
	 */
	private array $tags = [];

	/**
	 * The asset handles asked for, as a set, in the order asked.
	 *
	 * @var array<string, true>
	 */
	private array $handles = [];

	/**
	 * While held, the token in the head's and footer's placeholders.
	 */
	private ?string $held = null;

	/**
	 * Link `rel` values printed as resource hints, after the other links
	 * and before the styles they often serve.
	 */
	private const array HINTS = ['dns-prefetch', 'modulepreload', 'preconnect', 'prefetch', 'preload'];

	/**
	 * @param string $origin The site's origin (`https://example.com`), for
	 *                       resolving root-relative URLs. Empty leaves them.
	 */
	public function __construct(
		private readonly string $siteName = '',
		private readonly string $separator = ' | ',
		private readonly string $origin = '',
		private readonly ?Assets $assets = null
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
	 *
	 * In the footer, it prints just before `</body>`.
	 *
	 * @param array<string, string|bool> $attributes
	 */
	public function script(string $src, array $attributes = [], bool $footer = false): self
	{
		return $this->add("script:{$src}", 'script', ['src' => $src, 'defer' => ! isset($attributes['type']), ...$attributes], footer: $footer);
	}

	/**
	 * Adds an inline `<style>` block, once per id. The CSS must be
	 * trusted (it isn't escaped); `</style` is refused.
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
	 * refused. In the footer, it runs just before `</body>`, after the
	 * page's markup.
	 */
	public function inlineScript(string $id, string $js, bool $footer = false): self
	{
		if (str_contains(strtolower($js), '</script')) {
			throw new ViewException(sprintf('Inline script "%s" can\'t contain "</script".', $id));
		}

		return $this->add("inline-script:{$id}", 'script', ['id' => $id], $js, $footer);
	}

	/**
	 * Asks for registered assets by handle (D-570), such as
	 * `blush/player`. Their files are added when the head prints, after
	 * the assets they require, each once.
	 */
	public function enqueue(string ...$handles): self
	{
		foreach ($handles as $handle) {
			$this->handles[$handle] = true;
		}

		return $this;
	}

	/**
	 * Takes back assets asked for, so they don't print (unless an asset
	 * still asked for requires them).
	 */
	public function dequeue(string ...$handles): self
	{
		foreach ($handles as $handle) {
			unset($this->handles[$handle]);
		}

		return $this;
	}

	/**
	 * Returns whether an asset has been asked for.
	 */
	public function isEnqueued(string $handle): bool
	{
		return isset($this->handles[$handle]);
	}

	/**
	 * Returns the asset handles asked for, in the order asked.
	 *
	 * @return list<string>
	 */
	public function enqueued(): array
	{
		return array_keys($this->handles);
	}

	/**
	 * Returns whether an item has been added, by its key (`meta:{name}`,
	 * `property:{name}`, `link:canonical`, `style:{href}`,
	 * `inline-style:{id}`, `inline-script:{id}`, …).
	 */
	public function has(string $key): bool
	{
		return isset($this->tags[$key]);
	}

	/**
	 * Removes an item by its key (see `has()`), such as a theme stylesheet
	 * a standalone page doesn't want:
	 * `$template->head()->remove('style:' . $template->asset('style.css'))` (D-154).
	 */
	public function remove(string $key): self
	{
		unset($this->tags[$key]);

		return $this;
	}

	/**
	 * Renders the charset, the title, and every tag but the footer's,
	 * grouped by kind.
	 *
	 * @throws ThemeException When an asset's theme has an invalid build manifest.
	 */
	public function render(): string
	{
		$this->applyAssets();

		$lines = ['<meta charset="utf-8">', sprintf('<title>%s</title>', Escaper::html($this->documentTitle()))];
		$tags  = array_values(array_filter($this->tags, static fn (array $tag): bool => ! $tag[3]));

		usort($tags, fn(array $a, array $b): int => $this->group($a[0], $a[1]) <=> $this->group($b[0], $b[1]));

		foreach ($tags as [$element, $attributes, $content]) {
			$lines[] = $this->tag($element, $attributes, $content);
		}

		return "\t" . implode("\n\t", $lines);
	}

	/**
	 * Renders the footer's scripts, in the order they were added, or
	 * `''` when there are none.
	 *
	 * @throws ThemeException When an asset's theme has an invalid build manifest.
	 */
	public function foot(): string
	{
		$this->applyAssets();

		$lines = [];

		foreach ($this->tags as [$element, $attributes, $content, $footer]) {
			if ($footer) {
				$lines[] = $this->tag($element, $attributes, $content);
			}
		}

		return $lines === [] ? '' : "\t" . implode("\n\t", $lines);
	}

	/**
	 * Holds the head while a page renders: printing it leaves a
	 * placeholder until `fill()`. Returns whether this call held it, so
	 * a render inside another leaves filling to the outer one.
	 */
	public function hold(): bool
	{
		if ($this->held !== null) {
			return false;
		}

		$this->held = bin2hex(random_bytes(8));

		return true;
	}

	/**
	 * Fills a held page's HTML: the head where it was printed, and the
	 * footer's scripts just before the last `</body>` (or at the end,
	 * when there's none). Then the head is no longer held.
	 *
	 * @throws ThemeException When an asset's theme has an invalid build manifest.
	 */
	public function fill(string $html): string
	{
		if ($this->held === null) {
			return $html;
		}

		$placeholder = $this->placeholder();
		$this->held  = null;
		$html        = str_replace($placeholder, $this->render(), $html);
		$foot        = $this->foot();

		if ($foot === '') {
			return $html;
		}

		$end = strripos($html, '</body>');

		return $end === false ? "{$html}\n{$foot}\n" : substr($html, 0, $end) . "{$foot}\n" . substr($html, $end);
	}

	/**
	 * Prints the head, or its placeholder while it's held.
	 *
	 * @inheritDoc
	 */
	#[Override]
	public function __toString(): string
	{
		return $this->held === null ? $this->render() : $this->placeholder();
	}

	/**
	 * Returns the placeholder printed for the head while it's held.
	 */
	private function placeholder(): string
	{
		return "<!--blush-head-{$this->held}-->";
	}

	/**
	 * Adds the files of the assets asked for.
	 *
	 * @throws ThemeException
	 */
	private function applyAssets(): void
	{
		$this->assets?->apply($this, $this->enqueued());
	}

	/**
	 * Renders one tag.
	 *
	 * @param array<string, string|bool> $attributes
	 */
	private function tag(string $element, array $attributes, string $content): string
	{
		return match ($element) {
			'script', 'style' => sprintf('<%1$s%2$s>%3$s</%1$s>', $element, $this->attributes($attributes), $this->indent($content)),
			default           => sprintf('<%s%s>', $element, $this->attributes($attributes))
		};
	}

	/**
	 * Adds or replaces a tag.
	 *
	 * @param array<string, string|bool> $attributes
	 */
	private function add(string $key, string $element, array $attributes, string $content = '', bool $footer = false): self
	{
		$this->tags[$key] = [$element, $attributes, $content, $footer];

		return $this;
	}

	/**
	 * Returns an inline block's content on its own lines, indented by two
	 * tabs, with the closing tag back at one. Blank lines stay blank.
	 */
	private function indent(string $content): string
	{
		$content = trim($content, "\n");

		if ($content === '') {
			return '';
		}

		$lines = array_map(static fn(string $line): string => $line === '' ? '' : "\t\t{$line}", explode("\n", $content));

		return "\n" . implode("\n", $lines) . "\n\t";
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
			default                                                  => 5
		};
	}

	/**
	 * Renders HTML attributes. `true` prints a bare attribute and `false`
	 * leaves it out.
	 *
	 * @param array<string, string|bool> $attributes
	 */
	private function attributes(array $attributes): string
	{
		$html = '';

		foreach ($attributes as $name => $value) {
			if ($value === false) {
				continue;
			}

			$escaped = in_array($name, ['href', 'src'], true) ? Escaper::url($this->absolute($value === true ? '' : $value)) : Escaper::attr($value);
			$html   .= $value === true ? " {$name}" : sprintf(' %s="%s"', $name, $escaped);
		}

		return $html;
	}

	/**
	 * Returns a root-relative URL on the site's origin. Anything else
	 * (full URLs, protocol-relative ones, and fragments) is left alone.
	 */
	private function absolute(string $url): string
	{
		return $this->origin !== '' && str_starts_with($url, '/') && ! str_starts_with($url, '//')
			? rtrim($this->origin, '/') . $url
			: $url;
	}
}
