<?php

/**
 * Page markup.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View;

use JsonException;
use Psr\Log\LoggerInterface;
use Blush\Asset\Assets;
use Blush\Theme\ThemeException;

/**
 * The markup Blush adds to a page around its templates' own (D-578): one
 * keyed collection of tags, each with its `Placement`, written through
 * the page's `Head` (everything between `<head>` and `</head>`,
 * `$template->head()`) and `Foot` (the scripts at the end of the
 * `<body>`, `$template->foot()`).
 *
 * Each tag is keyed, so adding it twice keeps one copy (the later
 * value) in the place it was first added, whichever placement adds it;
 * asked for in both, it prints in the head, since earlier is always
 * safe. `has()` and `remove()` work across both. Registered assets are
 * asked for by handle (`enqueue('blush/player')`, D-570), and their
 * files are added, each in its placement, when the page prints.
 *
 * Inline scripts and data can be tied to a registered asset (D-580):
 * data (`data()`, JSON a script reads) prints just before the asset's
 * first script, and inline code (`inlineScript(after: …)`) just after
 * its last, as a module when that script is deferred, so the code runs
 * after it. Tied to an asset with no script on the page, they don't
 * print, which the log notes in development. Untied, data prints
 * before a placement's scripts and inline code after them (D-579).
 *
 * While a page renders, it's held (`hold()`): printing the head or the
 * foot leaves a placeholder, and `fill()` puts each in its place once
 * the whole page has rendered. So whatever renders after the layout
 * prints the head (the site footer, a component in it) can still add
 * to it. The foot prints where the layout prints `$template->foot()`,
 * the first time (D-577); nothing looks for `</body>`. A page that
 * doesn't print it gets no footer scripts, which `theme:check` reports
 * and, in development, the log notes.
 */
final class PageMarkup
{
	/**
	 * The page's `<head>`.
	 */
	public readonly Head $head;

	/**
	 * The end of the page's `<body>`.
	 */
	public readonly Foot $foot;

	/**
	 * Tags by key, in the order they were added: where it prints, the
	 * element, its attributes, its content (inline styles, scripts, and
	 * data only), and the asset handle it's tied to, if any.
	 *
	 * @var array<string, array{Placement, string, array<string, string|bool>, string, ?string}>
	 */
	private array $tags = [];

	/**
	 * Each asset's first and last script on the page, by handle: the
	 * keys of their tags, for what's tied to it.
	 *
	 * @var array<string, array{string, string}>
	 */
	private array $bound = [];

	/**
	 * The asset handles asked for, as a set, in the order asked.
	 *
	 * @var array<string, true>
	 */
	private array $handles = [];

	/**
	 * While held, the token in the placeholders.
	 */
	private ?string $held = null;

	/**
	 * @param string           $origin The site's origin (`https://example.com`), for
	 *                                 resolving root-relative URLs. Empty leaves them.
	 * @param ?LoggerInterface $logger Where to note footer scripts a page didn't
	 *                                 print, in development.
	 */
	public function __construct(
		string $siteName = '',
		string $separator = ' | ',
		private readonly string $origin = '',
		private readonly ?Assets $assets = null,
		private readonly ?LoggerInterface $logger = null
	) {
		$this->head = new Head($this, $siteName, $separator);
		$this->foot = new Foot($this);
	}

	/**
	 * Adds or replaces a tag. A tag already in the head stays there.
	 *
	 * @internal Used by `Head` and `Foot`.
	 * @param    array<string, string|bool> $attributes
	 */
	public function add(Placement $placement, string $key, string $element, array $attributes, string $content = '', ?string $handle = null): void
	{
		$placed = ($this->tags[$key][0] ?? null) === Placement::Head ? Placement::Head : $placement;

		$this->tags[$key] = [$placed, $element, $attributes, $content, $handle];
	}

	/**
	 * Adds an inline script, once per id; tied to an asset (`$after`), it
	 * asks for it and prints just after its last script.
	 *
	 * @internal Used by `Head` and `Foot`.
	 * @throws   ViewException When the JavaScript holds `</script`.
	 */
	public function addInlineScript(Placement $placement, string $id, string $js, ?string $after = null): void
	{
		if (str_contains(strtolower($js), '</script')) {
			throw new ViewException(sprintf('Inline script "%s" can\'t contain "</script".', $id));
		}

		if ($after !== null) {
			$this->enqueue($after);
		}

		$this->add($placement, "inline-script:{$id}", 'script', ['id' => $id], $js, $after);
	}

	/**
	 * Adds data for scripts to read (D-580), once per id: a value as
	 * JSON in `<script type="application/json" id="{id}">`, which never
	 * runs. A script reads it with
	 * `JSON.parse(document.getElementById(id).textContent)`. Tied to an
	 * asset (`$for`), it asks for it and prints just before its first
	 * script.
	 *
	 * @internal Used by `Head` and `Foot`.
	 * @throws   ViewException When the value can't be JSON.
	 */
	public function addData(Placement $placement, string $id, mixed $value, ?string $for = null): void
	{
		try {
			$json = json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
		} catch (JsonException $error) {
			throw new ViewException(sprintf('Data "%s" can\'t be JSON: %s', $id, $error->getMessage()), previous: $error);
		}

		if ($for !== null) {
			$this->enqueue($for);
		}

		$this->add($placement, "data:{$id}", 'script', ['type' => 'application/json', 'id' => $id], $json, $for);
	}

	/**
	 * Records an asset's first and last script on the page, for what's
	 * tied to it.
	 *
	 * @internal Used by `Assets` as it adds an asset's files.
	 */
	public function bind(string $handle, string $firstKey, string $lastKey): void
	{
		$this->bound[$handle] = [$firstKey, $lastKey];
	}

	/**
	 * Asks for registered assets by handle (D-570), such as
	 * `blush/player`. Their files are added when the page prints, after
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
	 * Returns whether a tag has been added, in either placement, by its
	 * key (`meta:{name}`, `property:{name}`, `link:canonical`,
	 * `style:{href}`, `script:{src}`, `inline-style:{id}`,
	 * `inline-script:{id}`, …).
	 */
	public function has(string $key): bool
	{
		return isset($this->tags[$key]);
	}

	/**
	 * Removes a tag by its key (see `has()`), such as a theme stylesheet
	 * a standalone page doesn't want:
	 * `$template->head()->remove('style:' . $template->asset('style.css'))` (D-154).
	 */
	public function remove(string $key): self
	{
		unset($this->tags[$key]);

		return $this;
	}

	/**
	 * Returns a placement's tags that aren't tied to an asset, in the
	 * order they were added, with the files of the assets asked for
	 * added first: each its key, element, attributes, and content.
	 *
	 * @internal Used by `Head` and `Foot` to render, before `arrange()`.
	 * @return   list<array{string, string, array<string, string|bool>, string}>
	 * @throws   ThemeException When an asset's theme has an invalid build manifest.
	 */
	public function tags(Placement $placement): array
	{
		$this->assets?->apply($this, $this->enqueued());

		$found = [];

		foreach ($this->tags as $key => [$placed, $element, $attributes, $content, $handle]) {
			if ($placed === $placement && $handle === null) {
				$found[] = [$key, $element, $attributes, $content];
			}
		}

		return $found;
	}

	/**
	 * Puts what's tied to an asset beside its scripts in a placement's
	 * tags, in their final order: data just before the first, inline
	 * code just after the last, as a module when that script is deferred
	 * (or a module itself), since an inline classic script would run
	 * before it.
	 *
	 * @internal Used by `Head` and `Foot` to render.
	 * @param    list<array{string, string, array<string, string|bool>, string}> $tags
	 * @return   list<array{string, string, array<string, string|bool>, string}>
	 */
	public function arrange(array $tags): array
	{
		$arranged = [];

		foreach ($tags as $tag) {
			[$key, , $attributes] = $tag;

			foreach ($this->tied($key, first: true) as $tiedKey => $tied) {
				$arranged[] = [$tiedKey, $tied[1], $tied[2], $tied[3]];
			}

			$arranged[] = $tag;

			$deferred = ($attributes['type'] ?? null) === 'module' || (($attributes['defer'] ?? false) === true && ($attributes['async'] ?? false) !== true);

			foreach ($this->tied($key, first: false) as $tiedKey => $tied) {
				$arranged[] = [$tiedKey, $tied[1], $deferred ? ['type' => 'module', ...$tied[2]] : $tied[2], $tied[3]];
			}
		}

		return $arranged;
	}

	/**
	 * Prints a placement: its markup, or its placeholder while the page
	 * is held.
	 *
	 * @internal Used by `Head` and `Foot` when they print.
	 * @throws   ThemeException
	 */
	public function print(Placement $placement): string
	{
		if ($this->held !== null) {
			return $this->placeholder($placement);
		}

		return $placement === Placement::Head ? $this->head->render() : $this->foot->render();
	}

	/**
	 * Holds the page while it renders: printing the head or the foot
	 * leaves a placeholder until `fill()`. Returns whether this call held
	 * it, so a render inside another leaves filling to the outer one.
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
	 * foot where it was first printed (printed twice, its scripts would
	 * run twice). Then the page is no longer held. A page with footer
	 * scripts that never printed the foot gets none, noted in the log in
	 * development.
	 *
	 * @param  string         $path The page's URL path, for the log.
	 * @throws ThemeException When an asset's theme has an invalid build manifest.
	 */
	public function fill(string $html, string $path = ''): string
	{
		if ($this->held === null) {
			return $html;
		}

		$head       = $this->placeholder(Placement::Head);
		$foot       = $this->placeholder(Placement::Foot);
		$this->held = null;
		$html       = str_replace($head, $this->head->render(), $html);
		$at         = strpos($html, $foot);

		$this->noteUntied($path);

		if ($at === false) {
			$this->noteUnprinted($path);

			return $html;
		}

		// A line of its own, since PHP drops the newline after a closing
		// tag, so the layout's `</body>` would follow on the same line.
		$scripts = $this->foot->render();
		$scripts = $scripts === '' ? '' : "{$scripts}\n";

		return substr($html, 0, $at) . $scripts . str_replace($foot, '', substr($html, $at + strlen($foot)));
	}

	/**
	 * Returns a script's attributes: its `src`, deferred unless the
	 * attributes say otherwise (`['defer' => false]`, or a `type` such
	 * as `module`).
	 *
	 * @internal Used by `Head` and `Foot`.
	 * @param    array<string, string|bool> $attributes
	 * @return   array<string, string|bool>
	 */
	public static function scriptAttributes(string $src, array $attributes): array
	{
		return ['src' => $src, 'defer' => ! isset($attributes['type']), ...$attributes];
	}

	/**
	 * Renders one tag.
	 *
	 * @internal Used by `Head` and `Foot` to render.
	 * @param    array<string, string|bool> $attributes
	 */
	public function tag(string $element, array $attributes, string $content): string
	{
		return match ($element) {
			'script' => sprintf('<script%s>%s</script>', $this->attributes($attributes), $this->code($content)),
			'style'  => sprintf('<style%s>%s</style>', $this->attributes($attributes), $this->indent($content)),
			default  => sprintf('<%s%s>', $element, $this->attributes($attributes))
		};
	}

	/**
	 * Returns the tags tied to the asset whose first (data) or last
	 * (inline code) script is a key.
	 *
	 * @return array<string, array{Placement, string, array<string, string|bool>, string, ?string}>
	 */
	private function tied(string $key, bool $first): array
	{
		$found = [];

		foreach ($this->tags as $tiedKey => $tag) {
			$bound = $tag[4] === null ? null : ($this->bound[$tag[4]] ?? null);

			if ($bound !== null && $bound[$first ? 0 : 1] === $key && str_starts_with($tiedKey, 'data:') === $first) {
				$found[$tiedKey] = $tag;
			}
		}

		return $found;
	}

	/**
	 * Notes, in development, what's tied to an asset with no script on
	 * the page, which didn't print.
	 */
	private function noteUntied(string $path): void
	{
		foreach ($this->tags as $key => $tag) {
			$bound = $tag[4] === null ? null : ($this->bound[$tag[4]] ?? null);

			if ($tag[4] !== null && ($bound === null || ! isset($this->tags[$bound[0]], $this->tags[$bound[1]]))) {
				$this->logger?->warning('{key} on {path} is tied to {handle}, which has no script on the page, so it didn\'t print.', [
					'key'    => $key,
					'path'   => $path === '' ? 'a page' : $path,
					'handle' => $tag[4]
				]);
			}
		}
	}

	/**
	 * Notes, in development, the footer scripts a page asked for that
	 * didn't print because its layout doesn't print `$template->foot()`.
	 *
	 * @throws ThemeException
	 */
	private function noteUnprinted(string $path): void
	{
		if ($this->logger === null) {
			return;
		}

		$lost = array_map(
			static fn (array $tag): string => is_string($tag[2]['src'] ?? null) ? $tag[2]['src'] : (is_string($tag[2]['id'] ?? null) ? "#{$tag[2]['id']}" : $tag[0]),
			$this->foot->arranged()
		);

		if ($lost !== []) {
			$this->logger->warning('The layout for {path} doesn\'t print $template->foot(), so its footer scripts didn\'t load: {scripts}. Print it just before </body>.', [
				'path'    => $path === '' ? 'a page' : $path,
				'scripts' => implode(', ', $lost)
			]);
		}
	}

	/**
	 * Returns the placeholder printed for a placement while held.
	 */
	private function placeholder(Placement $placement): string
	{
		return "<!--blush-{$placement->value}-{$this->held}-->";
	}

	/**
	 * Returns an inline script's code between its tags (D-580): trimmed,
	 * with no line breaks added, so a line of code prints as
	 * `<script>code</script>`. Code over several lines keeps its own
	 * line breaks (joining them could end a statement early or fold code
	 * into a `//` comment), each line after the first indented by one
	 * tab, as the page's tags are.
	 */
	private function code(string $content): string
	{
		$lines = explode("\n", trim($content));

		return implode("\n", array_map(static fn (string $line, int $index): string => $index === 0 || trim($line) === '' ? rtrim($line) : "\t{$line}", $lines, array_keys($lines)));
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
