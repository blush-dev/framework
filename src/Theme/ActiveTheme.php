<?php

/**
 * Active theme.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Theme;

/**
 * The theme a page renders with, and its parents, as templates read it
 * (D-815): every template has it as `$theme`. Its settings, and its own
 * files, each found through the chain, so a child theme's copy wins:
 *
 * ```php
 * <img src="<?= url($theme->asset('img/logo.png')) ?>" alt="">
 * <?= raw($theme->inline('svg/feed.svg')) ?>
 * <?php if ($theme->setting('dark_mode')) : ?> … <?php endif ?>
 * ```
 */
final readonly class ActiveTheme
{
	/**
	 * The theme's `vendor/name`.
	 */
	public string $name;

	public function __construct(
		private ThemeAssets $assets,
		private ThemeSettings $settings = new ThemeSettings()
	) {
		$this->name = $assets->chain->active()->name;
	}

	/**
	 * Returns a setting's value (from the theme's own group of settings,
	 * or its default), or `$default` when no theme in the chain declares
	 * it.
	 */
	public function setting(string $name, mixed $default = null): mixed
	{
		return $this->settings->get($name, $default);
	}

	/**
	 * Returns a file's URL (from a build manifest, or versioned by its
	 * modified time), or `''` when no theme has it.
	 *
	 * @throws ThemeException When a build manifest is invalid.
	 */
	public function asset(string $path): string
	{
		return $this->assets->url($path) ?? '';
	}

	/**
	 * Returns a file's contents, or `''` when no theme has it (D-151):
	 * `<?= raw($theme->inline('svg/github.svg')) ?>`. Only files a theme
	 * serves can be read, never views or PHP.
	 */
	public function inline(string $path): string
	{
		return $this->assets->contents($path);
	}
}
