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
use Stringable;

/**
 * Collects what goes in a page's `<head>`: the title, meta tags
 * (including OpenGraph properties), links (canonical, alternates,
 * pagination), stylesheets, and scripts. Templates and the renderer add
 * to it while the page renders, and the base layout prints it once with
 * `<?= $this->head() ?>`. Inline style blocks (`inlineStyle()`) hold
 * compiled design tokens. Since layouts render after the templates they
 * wrap, anything a template adds is in place by then.
 *
 * Each item is keyed, so adding it twice keeps one copy (the later value)
 * in the place it was first added.
 */
final class Head implements Stringable
{
	/**
	 * The page's own title, without the site name.
	 */
	private string $title = '';

	/**
	 * Tags by key, in the order they were added: the element, its
	 * attributes, and its content (inline styles only).
	 *
	 * @var array<string, array{string, array<string, string|bool>, string}>
	 */
	private array $tags = [];

	public function __construct(
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
	 * Adds a `<link>`. Links are keyed by `rel` and `href`, so a page can
	 * have several alternates.
	 *
	 * @param array<string, string|bool> $attributes
	 */
	public function link(string $rel, string $href, array $attributes = []): self
	{
		return $this->add("link:{$rel}:{$href}", 'link', ['rel' => $rel, 'href' => $href, ...$attributes]);
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
	 * @param array<string, string|bool> $attributes
	 */
	public function script(string $src, array $attributes = []): self
	{
		return $this->add("script:{$src}", 'script', ['src' => $src, 'defer' => ! isset($attributes['type']), ...$attributes]);
	}

	/**
	 * Adds an inline `<style>` block, once per id: compiled design tokens
	 * and per-entry overrides. The CSS must come from a trusted compiler
	 * (it isn't escaped); `</style` is refused.
	 */
	public function inlineStyle(string $id, string $css): self
	{
		if (str_contains(strtolower($css), '</style')) {
			throw new ViewException(sprintf('Inline style "%s" can\'t contain "</style".', $id));
		}

		return $this->add("inline-style:{$id}", 'style', ['id' => $id], $css);
	}

	/**
	 * Returns whether an item has been added, by its key (`meta:{name}`,
	 * `property:{name}`, `link:canonical`, `style:{href}`, …).
	 */
	public function has(string $key): bool
	{
		return isset($this->tags[$key]);
	}

	/**
	 * Removes an item by its key (see `has()`), such as a theme stylesheet
	 * a standalone page doesn't want:
	 * `$this->head()->remove('style:' . $this->asset('style.css'))` (D-154).
	 */
	public function remove(string $key): self
	{
		unset($this->tags[$key]);

		return $this;
	}

	/**
	 * Renders the title and every tag.
	 */
	public function render(): string
	{
		$html = sprintf('<title>%s</title>', Escaper::html($this->documentTitle()));

		foreach ($this->tags as [$element, $attributes, $content]) {
			$html .= "\n" . match ($element) {
				'script', 'style' => sprintf('<%1$s%2$s>%3$s</%1$s>', $element, self::attributes($attributes), $content === '' ? '' : "\n{$content}"),
				default           => sprintf('<%s%s>', $element, self::attributes($attributes))
			};
		}

		return $html;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function __toString(): string
	{
		return $this->render();
	}

	/**
	 * Adds or replaces a tag.
	 *
	 * @param array<string, string|bool> $attributes
	 */
	private function add(string $key, string $element, array $attributes, string $content = ''): self
	{
		$this->tags[$key] = [$element, $attributes, $content];

		return $this;
	}

	/**
	 * Renders HTML attributes. `true` prints a bare attribute and `false`
	 * leaves it out.
	 *
	 * @param array<string, string|bool> $attributes
	 */
	private static function attributes(array $attributes): string
	{
		$html = '';

		foreach ($attributes as $name => $value) {
			if ($value === false) {
				continue;
			}

			$escaped = in_array($name, ['href', 'src'], true) ? Escaper::url($value === true ? '' : $value) : Escaper::attr($value);
			$html   .= $value === true ? " {$name}" : sprintf(' %s="%s"', $name, $escaped);
		}

		return $html;
	}
}
