<?php

/**
 * Admin themes.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Admin\Theme;

use Blush\Extension\Requirement;

/**
 * The installed admin themes (D-827), as `AdminThemeDiscovery` (or its
 * cache) found them: valid themes by name, plus the broken ones, by where
 * they were found, with the reason. Which are on is `AdminThemeConfig`'s,
 * as icon packs' is `IconConfig`'s (D-390, D-391); Neutral, the admin's
 * own look, is always on.
 *
 * The themes an account may choose are the ones that are on with their
 * requirements met (`offered()`). An account's choice, or the site's
 * default when it has none, is the theme it sees (`inUse()`), falling
 * back to Neutral when that isn't offered.
 */
final readonly class AdminThemes
{
	/**
	 * Neutral's name: the admin's own tokens (`tokens.css`), which every
	 * other theme is laid over.
	 */
	public const string BASE = 'blush/neutral';

	/**
	 * @param array<string, AdminTheme>        $themes  Valid themes, by name.
	 * @param array<string, string>            $invalid Broken themes, by where they were found, with the reason.
	 * @param array<string, list<Requirement>> $unmet   The themes that are on but can't be offered, with the requirements each doesn't meet (D-431).
	 */
	public function __construct(
		private array $themes = [],
		private array $invalid = [],
		public AdminThemeConfig $config = new AdminThemeConfig(),
		private array $unmet = []
	) {}

	/**
	 * Returns a copy with config saying which are on.
	 */
	#[\NoDiscard]
	public function withConfig(AdminThemeConfig $config): self
	{
		return new self($this->themes, $this->invalid, $config, $this->unmet);
	}

	/**
	 * Returns a copy knowing which themes that are on can't be offered,
	 * and why (D-431).
	 *
	 * @param array<string, list<Requirement>> $unmet
	 */
	#[\NoDiscard]
	public function withUnmet(array $unmet): self
	{
		ksort($unmet);

		return new self($this->themes, $this->invalid, $this->config, $unmet);
	}

	/**
	 * Returns the themes that are on with their requirements met, by name.
	 *
	 * @return array<string, AdminTheme>
	 */
	public function enabled(): array
	{
		return array_diff_key($this->on(), $this->unmet);
	}

	/**
	 * Returns the themes that are on, by name, whether or not their
	 * requirements are met.
	 *
	 * @return array<string, AdminTheme>
	 */
	public function on(): array
	{
		return array_filter($this->themes, $this->isEnabled(...), ARRAY_FILTER_USE_KEY);
	}

	/**
	 * The themes that are on but can't be offered, by name, each with the
	 * requirements it doesn't meet.
	 *
	 * @return array<string, list<Requirement>>
	 */
	public function unmet(): array
	{
		return $this->unmet;
	}

	/**
	 * Whether a theme is on: Neutral always; another when it's installed
	 * and named by the admin's saved list, or, without one, from the
	 * framework or Composer, or named by config.
	 */
	public function isEnabled(string $name): bool
	{
		$theme = $this->themes[$name] ?? null;

		return match (true) {
			$theme === null               => false,
			$name === self::BASE          => true,
			$this->config->saved !== null => in_array($name, $this->config->saved, true),
			default                       => $theme->source !== AdminThemeSource::Local || in_array($name, $this->config->enabled, true)
		};
	}

	/**
	 * The config with a theme turned on as well (D-440): added to the
	 * admin's saved list, or, without one, to config's.
	 */
	public function configWith(string $name): AdminThemeConfig
	{
		return $this->config->saved !== null
			? new AdminThemeConfig($this->config->enabled, [...$this->config->saved, $name], $this->config->default)
			: new AdminThemeConfig([...$this->config->enabled, $name], null, $this->config->default);
	}

	/**
	 * Returns the themes an account may choose: Neutral first, then the
	 * others that are on with their requirements met, by label.
	 *
	 * @return list<AdminTheme>
	 */
	public function offered(): array
	{
		$offered = $this->enabled();

		uasort($offered, static fn (AdminTheme $a, AdminTheme $b): int => match (true) {
			$a->name === self::BASE => -1,
			$b->name === self::BASE => 1,
			default                 => strnatcasecmp($a->label, $b->label)
		});

		return array_values($offered);
	}

	/**
	 * Whether an account may choose a theme.
	 */
	public function isOffered(string $name): bool
	{
		return isset($this->enabled()[$name]);
	}

	/**
	 * Returns the theme an account sees: the one it chose, or, when it
	 * hasn't chosen one (or its choice isn't offered), the site's default,
	 * or Neutral. `null` only when Neutral itself can't be read.
	 */
	public function inUse(?string $choice = null): ?AdminTheme
	{
		$enabled = $this->enabled();

		return $enabled[$choice ?? ''] ?? $enabled[$this->config->default] ?? $enabled[self::BASE] ?? null;
	}

	/**
	 * Returns the site's default theme, as someone who hasn't chosen one
	 * sees it.
	 */
	public function default(): ?AdminTheme
	{
		return $this->inUse();
	}

	/**
	 * Returns a theme's colors for a preview of it (a swatch): its
	 * background, surface, accent, and text, each as CSS writes it, what
	 * it leaves out being Neutral's.
	 *
	 * @return array{background: string, surface: string, accent: string, text: string}
	 */
	public function preview(AdminTheme $theme): array
	{
		$tokens = $this->resolved($theme);

		return [
			'background' => $tokens->get(AdminToken::Background) ?? '',
			'surface'    => $tokens->get(AdminToken::Surface) ?? '',
			'accent'     => $tokens->get(AdminToken::Accent) ?? '',
			'text'       => $tokens->get(AdminToken::Text) ?? ''
		];
	}

	/**
	 * Returns a theme's palette, the six roles a site theme's preview
	 * declares (D-381), each its light and its dark color as CSS writes
	 * it, what it leaves out being Neutral's.
	 *
	 * @return array<string, array{string, string}>
	 */
	public function palette(AdminTheme $theme): array
	{
		$tokens = $this->resolved($theme);
		$roles  = [
			'background' => AdminToken::Background,
			'surface'    => AdminToken::Surface,
			'text'       => AdminToken::Text,
			'muted'      => AdminToken::TextMuted,
			'accent'     => AdminToken::Accent,
			'border'     => AdminToken::BorderColor
		];

		return array_map(static function (AdminToken $token) use ($tokens): array {
			$color = $tokens->color($token);

			return $color === null ? ['', ''] : [$color->light->css, $color->dark->css];
		}, $roles);
	}

	/**
	 * Returns Neutral's tokens with a theme's laid over them: everything
	 * the theme shows.
	 */
	public function resolved(AdminTheme $theme): AdminTokens
	{
		$base = $this->themes[self::BASE] ?? null;

		return $base === null || $base === $theme ? $theme->tokens : $base->tokens->with($theme->tokens);
	}

	/**
	 * Returns every valid theme, on or off, by name.
	 *
	 * @return array<string, AdminTheme>
	 */
	public function all(): array
	{
		return $this->themes;
	}

	/**
	 * Returns a theme, or `null` when it isn't installed.
	 */
	public function find(string $name): ?AdminTheme
	{
		return $this->themes[$name] ?? null;
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
	 * by where it was found, with a reason (a name another extension has,
	 * D-431).
	 *
	 * @param array<string, array{string, string}> $reasons Where each was found and why, by theme name.
	 */
	#[\NoDiscard]
	public function without(array $reasons): self
	{
		$themes  = $this->themes;
		$invalid = $this->invalid;

		foreach ($reasons as $name => [$where, $reason]) {
			if (isset($themes[$name])) {
				$invalid[$where] = $reason;
				unset($themes[$name]);
			}
		}

		return new self($themes, $invalid, $this->config, $this->unmet);
	}
}
