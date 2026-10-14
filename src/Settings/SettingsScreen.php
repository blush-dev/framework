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

use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * The admin's Settings screens with settings the site owner can change
 * (D-325), each a place field sets attach to (`settings:{screen}`,
 * D-343). System is all set in code, so it isn't one.
 */
enum SettingsScreen: string
{
	case General = 'general';
	case Reading = 'reading';
	case Writing = 'writing';
	case Media   = 'media';
	case Search  = 'search';
	case Ai      = 'ai';
	case Api     = 'api';

	/**
	 * Returns the screen's name, as the admin's navigation has it.
	 */
	public function label(Messages $messages): Text
	{
		return match ($this) {
			self::General => $messages->t('settings.settings_screen.general', 'General'),
			self::Reading => $messages->t('settings.settings_screen.reading', 'Reading'),
			self::Writing => $messages->t('settings.settings_screen.writing', 'Writing'),
			self::Media   => $messages->t('settings.settings_screen.media', 'Media'),
			self::Search  => $messages->t('settings.settings_screen.search', 'Addresses and Search'),
			self::Ai      => $messages->t('settings.settings_screen.ai', 'AI'),
			self::Api     => $messages->t('settings.settings_screen.api', 'API')
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
