<?php

/**
 * UUIDs.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Support;

use DateTimeInterface;

/**
 * Generates and checks UUIDs (RFC 9562), in-house (D-477). New ones are
 * version 7: a 48-bit Unix time in milliseconds, then random bits, so
 * they sort by when they were made, which keeps a database's index of
 * them in order. Written lowercase with hyphens:
 * `0199b6e2-7f3a-7c41-9d2e-5a8f0c3b1e74`.
 */
final class Uuid
{
	/**
	 * The namespace `fromName()` makes UUIDs in.
	 */
	private const string NAMESPACE = '6f1c2b7e-3d4a-4e9b-8c5f-2a7d9e0b1c43';

	/**
	 * Returns a new version 7 UUID for a time.
	 */
	public static function v7(DateTimeInterface $time): string
	{
		// Seconds and milliseconds, written together, are the milliseconds.
		$milliseconds = (int) $time->format('Uv') & 0xFFFFFFFFFFFF;
		$bytes        = substr(pack('J', $milliseconds), 2) . random_bytes(10);

		// The version (7) and variant (10xx) bits.
		$bytes[6] = chr(0x70 | (ord($bytes[6]) & 0x0F));
		$bytes[8] = chr(0x80 | (ord($bytes[8]) & 0x3F));

		$hex = bin2hex($bytes);

		return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
	}

	/**
	 * Returns the version 5 UUID for a name (SHA-1, RFC 9562), the same
	 * every time: for something stored before it had an id, so it has a
	 * steady one until an id is written for it (D-646).
	 */
	public static function fromName(string $name): string
	{
		$bytes = substr(sha1((string) hex2bin(str_replace('-', '', self::NAMESPACE)) . $name, true), 0, 16);

		// The version (5) and variant (10xx) bits.
		$bytes[6] = chr(0x50 | (ord($bytes[6]) & 0x0F));
		$bytes[8] = chr(0x80 | (ord($bytes[8]) & 0x3F));

		$hex = bin2hex($bytes);

		return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
	}

	/**
	 * Returns whether a value is a UUID in its usual form: 32 hex digits
	 * in groups of 8, 4, 4, 4, and 12, in either case. Any version is
	 * accepted (one copied from another system is still unique), but not
	 * the nil or max UUID, which aren't unique to anything.
	 */
	public static function isValid(mixed $value): bool
	{
		return is_string($value)
			&& preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/i', $value) === 1
			&& ! in_array(strtolower($value), ['00000000-0000-0000-0000-000000000000', 'ffffffff-ffff-ffff-ffff-ffffffffffff'], true);
	}
}
