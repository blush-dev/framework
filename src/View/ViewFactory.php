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

use Blush\Content\ContentRepository;
use Blush\Content\Entry\Entry;
use Blush\Content\Routing\ContentUrls;
use Blush\Core\AppConfig;
use Blush\Core\Paths;
use Blush\Routing\UrlGenerator;
use Blush\Theme\ThemeChain;
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
		private readonly ContentUrls $urls,
		private readonly ContentRepository $content,
		private readonly UrlGenerator $router,
		private readonly AppConfig $app
	) {}

	/**
	 * Returns the views for a theme chain.
	 */
	public function forChain(ThemeChain $chain): Views
	{
		return $this->views[$chain->active()->slug] ??= new Views(
			new ViewFinder($this->directories($chain)),
			$chain,
			$this->translator->withDirectories('theme', $chain->langDirectories()),
			$this->urls,
			$this->content,
			$this->router,
			$this->app
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
	 * Builds the context for a page: the `Head` with the site name and the
	 * active theme's stylesheets and scripts, `$site`, and the entry's
	 * presentation front matter (`layout` and `class`, D-027).
	 */
	public function context(Views $views, ?Entry $entry = null): ViewContext
	{
		$head   = new Head($this->app->name);
		$theme  = $views->chain->active();
		$layout = $entry?->field('layout');

		foreach ($theme->styles as $style) {
			$url = $views->chain->assetUrl($style);

			if ($url !== null) {
				$head->style($url);
			}
		}

		foreach ($theme->scripts as $script) {
			$url = $views->chain->assetUrl($script);

			if ($url !== null) {
				$head->script($url);
			}
		}

		$context = new ViewContext($head, ['site' => Site::fromConfig($this->app)], is_string($layout) ? $layout : null);
		$classes = $entry?->field('class');

		foreach (is_array($classes) ? $classes : [] as $class) {
			if (is_string($class)) {
				$context->addClass($class);
			}
		}

		return $context;
	}
}
