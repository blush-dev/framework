<?php

/**
 * API access.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Api;

use Blush\Translation\Messages;
use Blush\Translation\Text;

/**
 * Who may read the API (D-745): anyone, or only a request with an API
 * token, which makes the API private to a site's own front end, apps,
 * and agents.
 */
enum ApiAccess: string
{
	case Anyone = 'anyone';
	case Token  = 'token';

	/**
	 * Returns the choice's name, as the API screen has it.
	 */
	public function label(Messages $messages): Text
	{
		return match ($this) {
			self::Anyone => $messages->t('api.api_access.anyone.label', 'Anyone'),
			self::Token  => $messages->t('api.api_access.token.label', 'Only with a Token')
		};
	}

	/**
	 * Returns what the choice does, as the API screen says it.
	 */
	public function description(Messages $messages): Text
	{
		return match ($this) {
			self::Anyone => $messages->t('api.api_access.anyone.description', 'Anyone can read what the site shows. A token adds what its account may edit, when it asks.'),
			self::Token  => $messages->t('api.api_access.token.description', 'Every request needs an API token; one without is refused.')
		};
	}
}
