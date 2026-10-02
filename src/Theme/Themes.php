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
 * them: valid manifests by name (D-378), plus the themes whose manifests
 * are broken, by where they were found, with the reason. The framework
 * default theme, `blush/default`, is always last in a chain.
 */
final readonly class Themes
{
	/**
	 * The framework default theme's name.
	 */
	public const string DEFAULT = 'blush/default';

	/**
	 * @param array<string, ThemeManifest> $themes  Valid themes, by name.
	 * @param array<string, string>        $invalid Broken themes, by where they were found, with the reason.
	 */
	public function __construct(
		private array $themes,
		private array $invalid = []
	) {}

	/**
	 * Returns a theme's manifest, or `null` when it isn't installed.
	 */
	public function find(string $name): ?ThemeManifest
	{
		return $this->themes[$name] ?? null;
	}

	/**
	 * Returns whether a theme is installed with a valid manifest.
	 */
	public function has(string $name): bool
	{
		return isset($this->themes[$name]);
	}

	/**
	 * Returns the theme that claims a namespace, or `null`.
	 */
	public function byNamespace(string $namespace): ?ThemeManifest
	{
		return array_find($this->themes, static fn (ThemeManifest $theme): bool => $theme->namespace === $namespace);
	}

	/**
	 * Returns every valid theme, by name, the default theme first.
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
	 * Returns whether a namespace is an installed theme's, outside a
	 * chain. A component in such a theme's namespace (D-171) belongs to
	 * that theme, so it can't render in the chain; its provider may
	 * still have registered it when that theme is the active one.
	 */
	public function isOutside(string $namespace, ThemeChain $chain): bool
	{
		return $this->byNamespace($namespace) !== null && ! in_array($namespace, $chain->namespaces(), true);
	}

	/**
	 * Returns the broken themes, by where they were found, with the
	 * reason.
	 *
	 * @return array<string, string>
	 */
	public function invalid(): array
	{
		return $this->invalid;
	}

	/**
	 * Returns a copy without the named themes, each recorded as broken,
	 * by where it was found, with a reason (a namespace another extension
	 * claims, D-378). The default theme is never left out.
	 *
	 * @param array<string, array{string, string}> $reasons Where each was found and why, by theme name.
	 */
	public function without(array $reasons): self
	{
		$themes  = $this->themes;
		$invalid = $this->invalid;

		foreach ($reasons as $name => [$where, $reason]) {
			if (isset($themes[$name]) && $name !== self::DEFAULT) {
				$invalid[$where] = $reason;
				unset($themes[$name]);
			}
		}

		return new self($themes, $invalid);
	}

	/**
	 * Returns a theme's chain: the theme, its ancestors, then the default
	 * theme.
	 *
	 * @throws ThemeException When a theme in the chain is missing or
	 *         broken, or the chain loops.
	 */
	public function chain(string $name): ThemeChain
	{
		$themes = [];
		$next   = $name;

		while ($next !== null && $next !== self::DEFAULT) {
			if (isset($themes[$next])) {
				throw new ThemeException(sprintf('The "%s" theme\'s parents loop back to "%s".', $name, $next));
			}

			$theme = $this->find($next) ?? throw new ThemeException(
				$next === $name
					? sprintf('The "%s" theme is not installed%s.', $name, $this->brokenNote($next))
					: sprintf('The "%s" theme\'s ancestor "%s" is not installed%s.', $name, $next, $this->brokenNote($next))
			);

			$themes[$next] = $theme;
			$next          = $theme->parent;
		}

		$default = $this->find(self::DEFAULT) ?? throw new ThemeException('The framework default theme is missing.');

		return new ThemeChain([...array_values($themes), $default]);
	}

	/**
	 * Returns a note naming the broken themes whose folder matches a
	 * theme's name, when one may be the theme meant (its manifest is
	 * broken, so its name isn't known).
	 */
	private function brokenNote(string $name): string
	{
		$short = substr((string) strrchr("/{$name}", '/'), 1);
		$where = array_find_key($this->invalid, static fn (string $reason, string $where): bool => $where === $name || basename($where) === $short);

		return $where === null ? '' : sprintf(' (%s is broken: %s)', $where, $this->invalid[$where]);
	}
}
