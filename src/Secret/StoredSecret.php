<?php

/**
 * Stored secret.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Secret;

/**
 * What's known of a stored secret without decrypting it (D-715): whose
 * it is, when and by whom it was stored, and the id of the key it was
 * stored under. Never its value.
 */
final readonly class StoredSecret
{
	/**
	 * @param ?string $updated When it was stored, in UTC (ISO 8601).
	 * @param ?string $by      The id of the account that stored it, or `null` for code.
	 * @param ?string $keyId   The key it's under, or `null` for a value that isn't one.
	 */
	public function __construct(
		public string $owner,
		public string $key,
		public ?string $updated,
		public ?string $by,
		public ?string $keyId
	) {}

	/**
	 * Returns the full name, `{owner}.{key}`.
	 */
	public function name(): string
	{
		return "{$this->owner}.{$this->key}";
	}
}
