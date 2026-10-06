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
 * and the admin theme (D-317). The dashboard keeps two more (D-539): the
 * id of the entry the account last saved, for "You Were Editing", and
 * whether it skipped the setup path. And its shortcuts (D-547): the
 * screens pinned in Home's panel, in order, by the ids the admin gives
 * them, or `null` for the admin's default. Stored with the account;
 * settings at their defaults are left out.
 */
final readonly class Preferences
{
	/**
	 * @param ?list<string> $shortcuts
	 */
	public function __construct(
		public ColorScheme $colorScheme = ColorScheme::System,
		public AdminTheme $adminTheme = AdminTheme::Neutral,
		public ?string $lastEdited = null,
		public bool $setupSkipped = false,
		public ?array $shortcuts = null
	) {}

	/**
	 * The most shortcuts an account keeps.
	 */
	public const int MAX_SHORTCUTS = 30;

	/**
	 * Whether a value is a list of shortcut ids: up to `MAX_SHORTCUTS`
	 * distinct ids of lowercase letters, digits, `:`, `_`, and `-`.
	 *
	 * @phpstan-assert-if-true list<string> $value
	 */
	public static function isShortcuts(mixed $value): bool
	{
		return is_array($value)
			&& array_is_list($value)
			&& count($value) <= self::MAX_SHORTCUTS
			&& count(array_unique($value, SORT_REGULAR)) === count($value)
			&& array_all($value, static fn (mixed $id): bool => is_string($id) && preg_match('/^[a-z0-9][a-z0-9:_-]{0,79}$/', $id) === 1);
	}

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
	 * Returns a copy naming the entry the account last saved.
	 */
	#[NoDiscard]
	public function withLastEdited(?string $id): self
	{
		return clone($this, ['lastEdited' => $id]);
	}

	/**
	 * Returns a copy with the setup path skipped, or not.
	 */
	#[NoDiscard]
	public function withSetupSkipped(bool $skipped): self
	{
		return clone($this, ['setupSkipped' => $skipped]);
	}

	/**
	 * Returns a copy with other shortcuts, or `null` for the default.
	 *
	 * @param ?list<string> $shortcuts
	 */
	#[NoDiscard]
	public function withShortcuts(?array $shortcuts): self
	{
		return clone($this, ['shortcuts' => $shortcuts]);
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
		$edited = $data['lastEdited'] ?? null;

		return new self(
			colorScheme: (is_string($scheme) ? ColorScheme::tryFrom($scheme) : null) ?? ColorScheme::System,
			adminTheme: (is_string($theme) ? AdminTheme::tryFrom($theme) : null) ?? AdminTheme::Neutral,
			lastEdited: is_string($edited) && $edited !== '' ? $edited : null,
			setupSkipped: ($data['setupSkipped'] ?? false) === true,
			shortcuts: self::isShortcuts($data['shortcuts'] ?? null) ? $data['shortcuts'] : null
		);
	}

	/**
	 * Returns every preference by name.
	 *
	 * @return array{colorScheme: string, adminTheme: string, lastEdited: ?string, setupSkipped: bool, shortcuts: ?list<string>}
	 */
	public function toArray(): array
	{
		return [
			'colorScheme'  => $this->colorScheme->value,
			'adminTheme'   => $this->adminTheme->value,
			'lastEdited'   => $this->lastEdited,
			'setupSkipped' => $this->setupSkipped,
			'shortcuts'    => $this->shortcuts
		];
	}

	/**
	 * Returns the preferences that aren't at their defaults, for storing.
	 *
	 * @return array<string, string|bool|list<string>>
	 */
	public function changed(): array
	{
		$defaults = new self()->toArray();

		return array_filter($this->toArray(), static fn (string|bool|array|null $value, string $key): bool => $value !== null && $value !== $defaults[$key], ARRAY_FILTER_USE_BOTH);
	}
}
