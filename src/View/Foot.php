<?php

/**
 * Page foot.
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
 * What goes at the end of a page's `<body>` (D-578): scripts that load
 * after the page's markup. The base layout prints it just before
 * `</body>` with `<?= $template->foot() ?>` (D-577), and templates,
 * plugins, and themes add to it with `$template->foot()->script()` and
 * `inlineScript()`, or with a registered script that says `footer:
 * true`. Its tags are kept in the page's `PageMarkup`, which it shares
 * with the `Head`, so a tag asked for in both prints once, in the head.
 * Data prints first, then scripts, then inline scripts (D-579), each in
 * the order they were added, with what's tied to an asset beside its
 * scripts (D-580); every line is indented by one tab.
 */
final class Foot implements SafeHtml
{
	/**
	 * Built by its `PageMarkup` (`$markup->foot`).
	 *
	 * @internal
	 */
	public function __construct(private readonly PageMarkup $markup)
	{}

	/**
	 * Adds a script, once per URL. Scripts are deferred unless the
	 * attributes say otherwise (`['defer' => false]`, or `type: module`).
	 *
	 * @param array<string, string|bool> $attributes
	 */
	public function script(string $src, array $attributes = []): self
	{
		$this->markup->add(Placement::Foot, "script:{$src}", 'script', PageMarkup::scriptAttributes($src, $attributes));

		return $this;
	}

	/**
	 * Adds an inline `<script>`, once per id, which runs at the end of
	 * the body, after the page's markup. The JavaScript must be trusted
	 * (it isn't escaped); `</script` is refused. Code that needs a
	 * registered asset names it (`after: 'acme/gallery'`, D-580), and
	 * prints just after its last script, as `Head::inlineScript()`
	 * says.
	 *
	 * @throws ViewException
	 */
	public function inlineScript(string $id, string $js, ?string $after = null): self
	{
		$this->markup->addInlineScript(Placement::Foot, $id, $js, $after);

		return $this;
	}

	/**
	 * Adds data for scripts to read, once per id (D-580), as
	 * `Head::data()` says: JSON that never runs, before the foot's
	 * scripts, or just before the first script of the asset it's `for`.
	 *
	 * @throws ViewException When the value can't be JSON.
	 */
	public function data(string $id, mixed $value, ?string $for = null): self
	{
		$this->markup->addData(Placement::Foot, $id, $value, $for);

		return $this;
	}

	/**
	 * Returns whether a tag has been added, in the foot or the head, by
	 * its key; see `PageMarkup::has()`.
	 */
	public function has(string $key): bool
	{
		return $this->markup->has($key);
	}

	/**
	 * Removes a tag by its key; see `PageMarkup::has()`.
	 */
	public function remove(string $key): self
	{
		$this->markup->remove($key);

		return $this;
	}

	/**
	 * Returns the foot's tags in the order they print: data, scripts,
	 * then inline scripts (D-579), each in the order they were added,
	 * with what's tied to an asset beside its scripts (D-580).
	 *
	 * @return list<array{string, string, array<string, string|bool>, string}>
	 * @throws ThemeException When an asset's theme has an invalid build manifest.
	 */
	public function arranged(): array
	{
		$tags = $this->markup->tags(Placement::Foot);

		usort($tags, static fn (array $a, array $b): int => self::group($a[2]) <=> self::group($b[2]));

		return $this->markup->arrange($tags);
	}

	/**
	 * Returns the group a tag prints in: data, scripts, then inline
	 * scripts.
	 *
	 * @param array<string, string|bool> $attributes
	 */
	private static function group(array $attributes): int
	{
		return match (true) {
			($attributes['type'] ?? null) === 'application/json' => 0,
			isset($attributes['src'])                            => 1,
			default                                              => 2
		};
	}

	/**
	 * Renders the foot's tags (`arranged()`), or `''` when there are
	 * none.
	 *
	 * @throws ThemeException When an asset's theme has an invalid build manifest.
	 */
	public function render(): string
	{
		$lines = array_map(fn (array $tag): string => $this->markup->tag($tag[1], $tag[2], $tag[3]), $this->arranged());

		return $lines === [] ? '' : "\t" . implode("\n\t", $lines);
	}

	/**
	 * Prints the foot, or its placeholder while the page is held.
	 *
	 * @inheritDoc
	 */
	#[Override]
	public function __toString(): string
	{
		return $this->markup->print(Placement::Foot);
	}
}
