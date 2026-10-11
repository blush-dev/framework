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
	public function label(): string
	{
		return match ($this) {
			self::Anyone => 'Anyone',
			self::Token  => 'Only with a Token'
		};
	}

	/**
	 * Returns what the choice does, as the API screen says it.
	 */
	public function description(): string
	{
		return match ($this) {
			self::Anyone => 'Anyone can read what the site shows. A token adds what its account may edit, when it asks.',
			self::Token  => 'Every request needs an API token; one without is refused.'
		};
	}
}
