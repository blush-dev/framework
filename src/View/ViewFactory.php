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

use Blush\Content\Entry\Entry;
use Blush\Core\Paths;
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
 * Views resolve through the site's overrides first
 * (`resources/views/themes/{active}`, then `resources/views`), then the
 * chain's themes (D-024). The translator's `theme` domain reads the
 * chain's `lang` folders.
 */
final class ViewFactory
{
	/**
	 * Views built so far, by active theme slug.
	 *
	 * @var array<string, Views>
	 */
	private array $views = [];

	public function __construct(
		private readonly Paths $paths,
		private readonly Translator $translator,
		private readonly ViewServices $services,
		private readonly SettingsResolver $settings
	) {}

	/**
	 * Returns the views for a theme chain.
	 *
	 * @throws ThemeException When the chain's settings are invalid.
	 * @throws InvalidData When the site's theme data can't be read.
	 */
	public function forChain(ThemeChain $chain): Views
	{
		return $this->views[$chain->active()->slug] ??= new Views(
			new ViewFinder($this->directories($chain)),
			new ThemeAssets($chain),
			$this->translator->withDirectories('theme', $chain->langDirectories()),
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
		return [
			"{$this->paths->resources}/views/themes/{$chain->active()->slug}",
			"{$this->paths->resources}/views",
			...$chain->viewDirectories()
		];
	}

	/**
	 * Builds the context for a page: the `Head` with the site name and
	 * the active theme's stylesheets and scripts (with any stylesheets a
	 * build manifest pairs with them; built scripts load as modules),
	 * `$site`, and the entry's presentation front matter (`layout`,
	 * `class`, and `stylesheet`, D-027).
	 *
	 * @throws ThemeException When a build manifest is invalid.
	 */
	public function context(Views $views, ?Entry $entry = null): ViewContext
	{
		$head  = new Head($this->services->app->name, origin: $this->services->app->origin());
		$theme = $views->chain->active();

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

		if ($entry !== null) {
			$this->presentation($views, $head, $entry);
		}

		$layout  = $entry?->field('layout');
		$context = new ViewContext($head, ['site' => Site::fromConfig($this->services->app)], is_string($layout) ? $layout : null);
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
	 * as a component in Markdown.
	 */
	public function fragment(): ViewContext
	{
		return new ViewContext(new Head($this->services->app->name, origin: $this->services->app->origin()), ['site' => Site::fromConfig($this->services->app)]);
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
