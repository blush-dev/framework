<?php

/**
 * Secret status.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Secret;

/**
 * Where a secret's value comes from, if anywhere (D-715).
 */
enum SecretStatus: string
{
	/**
	 * Set in the environment (or `.env`), which always wins.
	 */
	case Environment = 'environment';

	/**
	 * Stored, encrypted, and readable with the site's key.
	 */
	case Stored = 'stored';

	/**
	 * Set nowhere.
	 */
	case Missing = 'missing';

	/**
	 * Stored, but it won't decrypt.
	 */
	case Unreadable = 'unreadable';

	/**
	 * Whether there's a value to use.
	 */
	public function isSet(): bool
	{
		return $this === self::Environment || $this === self::Stored;
	}
}
