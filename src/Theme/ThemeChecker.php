<?php

/**
 * Theme checker.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Theme;

use Throwable;
use Dom\Element;
use Dom\HTMLDocument;
use Blush\Content\Http\ContentPage;
use Blush\Content\Http\PageKind;
use Blush\Content\Schema\Severity;
use Blush\Content\Schema\Violation;
use Blush\Core\ServiceProvider;
use Blush\Theme\Token\Contrast;
use Blush\Theme\Token\TokenResolver;
use Blush\View\ViewFactory;

/**
 * Checks a theme for `theme:check` (D-020, D-030, D-032):
 *
 * - **Errors:** a chain that doesn't resolve; a provider that isn't a
 *   service provider; invalid setting definitions or tokens files; text
 *   colors below WCAG AA contrast (4.5:1) in any mode; a base layout
 *   without `lang` on `<html>`, one `<main>`, or a skip link to it.
 * - **Warnings:** shadowed manifests (JSON wins); site setting values
 *   that don't fit; tokens that can't compile or whose aliases don't
 *   resolve; other broken themes; a layout without `<header>` or
 *   `<footer>`, or with other than one `<h1>`.
 * - **Notices:** colors whose contrast can't be measured, and
 *   `requires` entries, which aren't enforced yet.
 *
 * Contrast pairs are `[foreground, background]` token paths: the
 * default theme's list, or the nearest `contrast` list in the chain.
 * The layout is checked by rendering the `welcome` page.
 */
final readonly class ThemeChecker
{
	public function __construct(
		private Themes $themes,
		private SettingsResolver $settings,
		private TokenResolver $tokens,
		private ViewFactory $views
	) {}

	/**
	 * Checks a theme and its chain.
	 */
	public function check(string $slug): ThemeReport
	{
		$problems = [];

		foreach ($this->themes->invalid() as $invalid => $message) {
			if ($invalid !== $slug) {
				$problems[] = new Violation("theme {$invalid}", $message, Severity::Warning);
			}
		}

		try {
			$chain = $this->themes->chain($slug);
		} catch (ThemeException $error) {
			return new ThemeReport($slug, [new Violation('manifest', $error->getMessage()), ...$problems]);
		}

		foreach ($chain as $theme) {
			$problems = [...$problems, ...$this->manifest($theme)];
		}

		$problems = [...$problems, ...$this->settings($chain), ...$this->tokens($chain), ...$this->layout($chain)];

		return new ThemeReport($slug, $problems);
	}

	/**
	 * Checks a theme's manifest files and provider.
	 *
	 * @return list<Violation>
	 */
	private function manifest(ThemeManifest $theme): array
	{
		$problems = [];

		foreach (array_slice(ThemeDiscovery::manifestFiles($theme->path), 1) as $shadowed) {
			$problems[] = new Violation('manifest', sprintf('%s is ignored; the "%s" theme\'s %s wins (D-032).', basename($shadowed), $theme->slug, basename(ThemeDiscovery::manifestFiles($theme->path)[0])), Severity::Warning);
		}

		if ($theme->provider !== null && ! is_subclass_of($theme->provider, ServiceProvider::class)) {
			$problems[] = new Violation('provider', sprintf('The "%s" theme\'s provider %s isn\'t a service provider class (check its "autoload").', $theme->slug, $theme->provider));
		}

		if (isset($theme->data['requires'])) {
			$problems[] = new Violation('requires', sprintf('The "%s" theme\'s "requires" isn\'t checked yet.', $theme->slug), Severity::Notice);
		}

		return $problems;
	}

	/**
	 * Checks the chain's settings.
	 *
	 * @return list<Violation>
	 */
	private function settings(ThemeChain $chain): array
	{
		try {
			return array_map(
				static fn (Violation $violation): Violation => new Violation("setting {$violation->field}", $violation->message, Severity::Warning),
				$this->settings->for($chain)->violations
			);
		} catch (Throwable $error) {
			return [new Violation('settings', $error->getMessage())];
		}
	}

	/**
	 * Checks the chain's tokens and their contrast.
	 *
	 * @return list<Violation>
	 */
	private function tokens(ThemeChain $chain): array
	{
		try {
			$set = $this->tokens->for($chain);
		} catch (Throwable $error) {
			return [new Violation('tokens', $error->getMessage())];
		}

		$problems = array_map(
			static fn (string $problem): Violation => new Violation('tokens', $problem, Severity::Warning),
			[...$set->problems, ...$set->compileProblems()]
		);

		foreach (self::pairs($chain) as [$foreground, $background]) {
			foreach ([null, ...$set->modes()] as $mode) {
				$fg = $set->value($foreground, $mode);
				$bg = $set->value($background, $mode);

				if ($fg === null || $bg === null) {
					continue;
				}

				$ratio = Contrast::ratio($fg, $bg);
				$where = sprintf('%s on %s%s', $foreground, $background, $mode === null ? '' : " ({$mode})");

				if ($ratio === null) {
					$problems[] = new Violation('contrast', sprintf('%s: can\'t measure %s on %s.', $where, $fg, $bg), Severity::Notice);
				} elseif ($ratio < Contrast::AA) {
					$problems[] = new Violation('contrast', sprintf('%s is %.2f:1; WCAG AA needs %.1f:1.', $where, $ratio, Contrast::AA));
				}
			}
		}

		return $problems;
	}

	/**
	 * Returns the contrast pairs: the nearest theme's `contrast` list.
	 *
	 * @return list<array{string, string}>
	 */
	private static function pairs(ThemeChain $chain): array
	{
		foreach ($chain as $theme) {
			$pairs = $theme->data['contrast'] ?? null;

			if (! is_array($pairs)) {
				continue;
			}

			$valid = [];

			foreach ($pairs as $pair) {
				if (is_array($pair) && is_string($pair[0] ?? null) && is_string($pair[1] ?? null)) {
					$valid[] = [$pair[0], $pair[1]];
				}
			}

			return $valid;
		}

		return [];
	}

	/**
	 * Renders the welcome page and checks its landmarks.
	 *
	 * @return list<Violation>
	 */
	private function layout(ThemeChain $chain): array
	{
		try {
			$views = $this->views->forChain($chain);
			$html  = $views->render('welcome', ['page' => new ContentPage(PageKind::Welcome, ''), 'title' => ''], $this->views->context($views));
		} catch (Throwable $error) {
			return [new Violation('layout', sprintf('The welcome page doesn\'t render: %s', $error->getMessage()))];
		}

		$document = HTMLDocument::createFromString($html, LIBXML_NOERROR);
		$problems = [];
		$root     = $document->documentElement;
		$mains    = $document->querySelectorAll('main');

		if (! $root instanceof Element || trim((string) $root->getAttribute('lang')) === '') {
			$problems[] = new Violation('layout', 'The base layout\'s <html> has no lang attribute.');
		}

		if ($mains->length !== 1) {
			$problems[] = new Violation('layout', sprintf('The base layout needs one <main> landmark; it has %d.', $mains->length));
		}

		$main = $mains->item(0);
		$skip = $document->querySelector('body a[href^="#"]');
		$id   = $skip instanceof Element ? substr((string) $skip->getAttribute('href'), 1) : '';

		if ($id === '' || ! $main instanceof Element || ($main->getAttribute('id') !== $id && $main->querySelector('[id="' . addslashes($id) . '"]') === null)) {
			$problems[] = new Violation('layout', 'The base layout needs a skip link (the first in-page link) to the <main> content.');
		}

		foreach (['header', 'footer'] as $landmark) {
			if (! self::hasLandmark($document, $landmark)) {
				$problems[] = new Violation('layout', "The base layout has no <{$landmark}> landmark.", Severity::Warning);
			}
		}

		$headings = $document->querySelectorAll('h1')->length;

		if ($headings !== 1) {
			$problems[] = new Violation('layout', sprintf('The welcome page has %d <h1> headings; pages should have one.', $headings), Severity::Warning);
		}

		return $problems;
	}

	/**
	 * Returns whether a page has a `<header>` or `<footer>` that's a
	 * landmark: one not inside sectioning content (an article's header
	 * isn't the page's banner).
	 */
	private static function hasLandmark(HTMLDocument $document, string $element): bool
	{
		foreach ($document->querySelectorAll($element) as $candidate) {
			if ($candidate->closest('article, aside, main, nav, section') === null) {
				return true;
			}
		}

		return false;
	}
}
