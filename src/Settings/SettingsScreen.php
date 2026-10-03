<?php

/**
 * Settings screen.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Settings;

/**
 * The admin's Settings screens with settings the site owner can change
 * (D-325), each a place field sets attach to (`settings:{screen}`,
 * D-343). System is all set in code, so it isn't one.
 */
enum SettingsScreen: string
{
	case General = 'general';
	case Reading = 'reading';
	case Search  = 'search';
	case Ai      = 'ai';

	/**
	 * Returns the screen's name, as the admin's navigation has it.
	 */
	public function label(): string
	{
		return match ($this) {
			self::General => 'General',
			self::Reading => 'Reading',
			self::Search  => 'Addresses and Search',
			self::Ai      => 'AI'
		};
	}

	/**
	 * Returns the built-in settings the screen edits, in order.
	 *
	 * @return list<Setting>
	 */
	public function settings(): array
	{
		return array_values(array_filter(Setting::cases(), fn (Setting $setting): bool => $setting->screen() === $this));
	}
}
