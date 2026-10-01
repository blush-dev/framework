<?php

/**
 * Account preferences.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Auth;

use NoDiscard;

/**
 * How an account likes the admin (D-235): settings that belong to the
 * person, not the site, and follow them to any device: the color scheme,
 * and the admin theme (D-317). Stored with the
 * account; settings at their defaults are left out.
 */
final readonly class Preferences
{
	public function __construct(
		public ColorScheme $colorScheme = ColorScheme::System,
		public AdminTheme $adminTheme = AdminTheme::Neutral
	) {}

	/**
	 * Returns a copy with another color scheme.
	 */
	#[NoDiscard]
	public function withColorScheme(ColorScheme $scheme): self
	{
		return clone($this, ['colorScheme' => $scheme]);
	}

	/**
	 * Returns a copy with another admin theme.
	 */
	#[NoDiscard]
	public function withAdminTheme(AdminTheme $theme): self
	{
		return clone($this, ['adminTheme' => $theme]);
	}

	/**
	 * Builds preferences from their stored array. Unknown keys and values
	 * fall back to the defaults, so a damaged setting never locks anyone
	 * out.
	 *
	 * @param array<mixed> $data
	 */
	public static function fromArray(array $data): self
	{
		$scheme = $data['colorScheme'] ?? null;
		$theme  = $data['adminTheme'] ?? null;

		return new self(
			colorScheme: (is_string($scheme) ? ColorScheme::tryFrom($scheme) : null) ?? ColorScheme::System,
			adminTheme: (is_string($theme) ? AdminTheme::tryFrom($theme) : null) ?? AdminTheme::Neutral
		);
	}

	/**
	 * Returns every preference by name.
	 *
	 * @return array{colorScheme: string, adminTheme: string}
	 */
	public function toArray(): array
	{
		return ['colorScheme' => $this->colorScheme->value, 'adminTheme' => $this->adminTheme->value];
	}

	/**
	 * Returns the preferences that aren't at their defaults, for storing.
	 *
	 * @return array<string, string>
	 */
	public function changed(): array
	{
		return array_diff_assoc($this->toArray(), new self()->toArray());
	}
}
