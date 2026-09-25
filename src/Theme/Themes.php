<?php

/**
 * Installed themes.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Theme;

/**
 * The installed themes, as `ThemeDiscovery` (or the theme cache) found
 * them: valid manifests by slug, plus the themes whose manifests are
 * broken, with the reason. The framework default theme, `default`, is
 * always last in a chain.
 */
final readonly class Themes
{
	/**
	 * The framework default theme's slug.
	 */
	public const string DEFAULT = 'default';

	/**
	 * @param array<string, ThemeManifest> $themes  Valid themes, by slug.
	 * @param array<string, string>        $invalid Broken themes, by slug, with the reason.
	 */
	public function __construct(
		private array $themes,
		private array $invalid = []
	) {}

	/**
	 * Returns whether a string is a valid theme slug.
	 */
	public static function isValidSlug(string $slug): bool
	{
		return preg_match('/^[a-z0-9][a-z0-9_-]*$/', $slug) === 1;
	}

	/**
	 * Returns a theme's manifest, or `null` when it isn't installed.
	 *
	 * @throws ThemeException When its manifest is broken.
	 */
	public function find(string $slug): ?ThemeManifest
	{
		if (isset($this->invalid[$slug])) {
			throw new ThemeException($this->invalid[$slug]);
		}

		return $this->themes[$slug] ?? null;
	}

	/**
	 * Returns whether a theme is installed with a valid manifest.
	 */
	public function has(string $slug): bool
	{
		return isset($this->themes[$slug]);
	}

	/**
	 * Returns every valid theme, by slug, the default theme first.
	 *
	 * @return array<string, ThemeManifest>
	 */
	public function all(): array
	{
		$themes = $this->themes;

		if (isset($themes[self::DEFAULT])) {
			$themes = [self::DEFAULT => $themes[self::DEFAULT], ...$themes];
		}

		return $themes;
	}

	/**
	 * Returns the broken themes, by slug, with the reason.
	 *
	 * @return array<string, string>
	 */
	public function invalid(): array
	{
		return $this->invalid;
	}

	/**
	 * Returns a theme's chain: the theme, its ancestors, then the default
	 * theme.
	 *
	 * @throws ThemeException When a theme in the chain is missing or
	 *         broken, or the chain loops.
	 */
	public function chain(string $slug): ThemeChain
	{
		$themes = [];
		$next   = $slug;

		while ($next !== null && $next !== self::DEFAULT) {
			if (isset($themes[$next])) {
				throw new ThemeException(sprintf('The "%s" theme\'s parents loop back to "%s".', $slug, $next));
			}

			$theme = $this->find($next) ?? throw new ThemeException(
				$next === $slug
					? sprintf('The "%s" theme is not installed.', $slug)
					: sprintf('The "%s" theme\'s ancestor "%s" is not installed.', $slug, $next)
			);

			$themes[$next] = $theme;
			$next          = $theme->parent;
		}

		$default = $this->find(self::DEFAULT) ?? throw new ThemeException('The framework default theme is missing.');

		return new ThemeChain([...array_values($themes), $default]);
	}
}
