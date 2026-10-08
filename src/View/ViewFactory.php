<?php

/**
 * View factory.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\View;

use Psr\Log\LoggerInterface;
use Blush\Content\Entry\Entry;
use Blush\Core\Framework;
use Blush\Data\InvalidData;
use Blush\Theme\SettingsResolver;
use Blush\Theme\ThemeAssets;
use Blush\Theme\ThemeChain;
use Blush\Theme\ThemeException;
use Blush\Translation\Translator;

/**
 * Builds the `Views` for a theme chain, once per chain, and the context
 * a page renders in.
 *
 * Views resolve through the chain's themes, the active theme first
 * (D-024); the site has no views of its own (D-617). The translator's `theme` domain reads the
 * chain's `lang` folders.
 */
final class ViewFactory
{
	/**
	 * Views built so far, by active theme name.
	 *
	 * @var array<string, Views>
	 */
	private array $views = [];

	public function __construct(
		private readonly Translator $translator,
		private readonly ViewServices $services,
		private readonly SettingsResolver $settings,
		private readonly ?LoggerInterface $logger = null
	) {}

	/**
	 * Returns the views for a theme chain.
	 *
	 * @throws ThemeException When the chain's settings are invalid.
	 * @throws InvalidData When the site's theme data can't be read.
	 */
	public function forChain(ThemeChain $chain): Views
	{
		return $this->views[$chain->active()->name] ??= new Views(
			new ViewFinder($this->directories($chain), $this->services->engines->extensions()),
			new ThemeAssets($chain),
			$this->translator->withDomains($chain->langDirectories(), $chain->namespaceDomains()),
			$this->services,
			$this->settings->for($chain)
		);
	}

	/**
	 * Returns the view directories for a chain, highest precedence first.
	 *
	 * @return list<string>
	 */
	public function directories(ThemeChain $chain): array
	{
		return $chain->viewDirectories();
	}

	/**
	 * Builds the context for a page: its `PageMarkup` (D-578), with the
	 * site name in the head and, in development, the log for footer
	 * scripts a layout doesn't print; the `viewport` and `generator` meta
	 * tags (D-472), and the active
	 * theme's stylesheets and scripts (with any stylesheets a
	 * build manifest pairs with them; built scripts load as modules) and
	 * the files it preloads (D-558),
	 * `$site`, the entry's presentation front matter (`layout`,
	 * `class`, and `stylesheet`, D-027), and the page's URL path and
	 * locale (a list's language's, D-455, else the entry's, else the
	 * site's).
	 *
	 * @throws ThemeException When a build manifest is invalid.
	 */
	public function context(Views $views, ?Entry $entry = null, string $path = '', ?string $language = null): ViewContext
	{
		$markup = new PageMarkup(
			$this->services->app->name,
			origin: $this->services->app->origin(),
			assets: $this->services->assets,
			logger: $this->services->app->environment->isDevelopment() ? $this->logger : null
		);
		$head   = $markup->head;
		$theme  = $views->chain->active();

		$head->meta('viewport', 'width=device-width, initial-scale=1');
		$head->meta('generator', Framework::NAME . ' ' . Framework::VERSION);

		foreach ([...$theme->styles, ...$theme->scripts] as $asset) {
			foreach ($views->assets->css($asset) as $url) {
				$head->style($url);
			}
		}

		foreach ($theme->styles as $style) {
			$url = $views->assets->url($style);

			if ($url !== null && ! str_ends_with(strtok($url, '?') ?: '', '.js')) {
				$head->style($url);
			}
		}

		foreach ($theme->scripts as $script) {
			$url = $views->assets->url($script);

			if ($url !== null) {
				$head->script($url, $views->assets->isBuilt($script) ? ['type' => 'module'] : []);
			}
		}

		foreach ($theme->preload as $file) {
			$url = $views->assets->url($file);

			if ($url !== null) {
				$head->preload($url);
			}
		}

		if ($entry !== null) {
			$this->presentation($views, $head, $entry);
		}

		$layout  = $entry?->field('layout');
		$locale  = ($language === null ? null : $this->services->app->languages->find($language)?->locale) ?? $entry->locale ?? $this->services->app->locale;
		$language ??= $entry?->language;
		$context = new ViewContext(
			$markup,
			['site' => Site::fromConfig($this->services->app, $locale)],
			is_string($layout) ? $layout : null,
			$path,
			$locale,
			$language !== null && $this->services->app->languages->isOther($language) ? $language : ''
		);
		$classes = $entry?->field('class');

		foreach (is_array($classes) ? $classes : [] as $class) {
			if (is_string($class)) {
				$context->addClass($class);
			}
		}

		return $context;
	}

	/**
	 * Builds a bare context for a fragment rendered outside a page, such
	 * as a directive in Markdown, in a language other than the default
	 * when it's given one (D-459): a translation's body.
	 */
	public function fragment(string $language = ''): ViewContext
	{
		$found  = $language === '' ? null : $this->services->app->languages->find($language);
		$locale = $found->locale ?? $this->services->app->locale;

		return new ViewContext(
			new PageMarkup($this->services->app->name, origin: $this->services->app->origin()),
			['site' => Site::fromConfig($this->services->app, $locale)],
			locale: $locale,
			language: $found !== null && $this->services->app->languages->isOther($found->code) ? $found->code : ''
		);
	}

	/**
	 * Adds an entry's own stylesheet to the head.
	 *
	 * @throws ThemeException
	 */
	private function presentation(Views $views, Head $head, Entry $entry): void
	{
		$stylesheet = $entry->field('stylesheet');

		if (is_string($stylesheet) && $stylesheet !== '') {
			$url = preg_match('#^(https?:)?//|^/#', $stylesheet) === 1 ? $stylesheet : $views->assets->url(ltrim($stylesheet, './'));

			if ($url !== null) {
				$head->style($url);
			}
		}
	}
}
