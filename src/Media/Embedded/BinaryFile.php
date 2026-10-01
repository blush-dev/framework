<?php

/**
 * Binary file.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Media\Embedded;

/**
 * Reads parts of a file by offset (D-291), for the sound and video
 * readers, which look at a file's structure without loading it whole.
 * Reads past the end give what's there; a file that can't be opened
 * reads as empty.
 */
final class BinaryFile
{
	/**
	 * @var ?resource
	 */
	private $handle;

	public readonly int $size;

	public function __construct(string $path)
	{
		$handle       = @fopen($path, 'rb');
		$this->handle = $handle === false ? null : $handle;
		$size         = @filesize($path);
		$this->size   = $size === false ? 0 : $size;
	}

	public function __destruct()
	{
		if ($this->handle !== null) {
			fclose($this->handle);
		}
	}

	/**
	 * Up to `length` bytes from `offset`.
	 */
	public function read(int $offset, int $length): string
	{
		if ($this->handle === null || $offset < 0 || $length <= 0 || $offset >= $this->size || fseek($this->handle, $offset) !== 0) {
			return '';
		}

		$bytes = fread($this->handle, $length);

		return $bytes === false ? '' : $bytes;
	}

	public static function uint16be(string $bytes, int $at = 0): int
	{
		return strlen($bytes) >= $at + 2 ? (ord($bytes[$at]) << 8) | ord($bytes[$at + 1]) : 0;
	}

	public static function uint16le(string $bytes, int $at = 0): int
	{
		return strlen($bytes) >= $at + 2 ? ord($bytes[$at]) | (ord($bytes[$at + 1]) << 8) : 0;
	}

	public static function uint32be(string $bytes, int $at = 0): int
	{
		return strlen($bytes) >= $at + 4 ? self::unpacked('N', $bytes, $at) : 0;
	}

	public static function uint32le(string $bytes, int $at = 0): int
	{
		return strlen($bytes) >= $at + 4 ? self::unpacked('V', $bytes, $at) : 0;
	}

	public static function uint64be(string $bytes, int $at = 0): int
	{
		return strlen($bytes) >= $at + 8 ? self::unpacked('J', $bytes, $at) : 0;
	}

	public static function uint64le(string $bytes, int $at = 0): int
	{
		return strlen($bytes) >= $at + 8 ? self::unpacked('P', $bytes, $at) : 0;
	}

	private static function unpacked(string $format, string $bytes, int $at): int
	{
		$values = unpack($format, $bytes, $at);

		return is_array($values) && is_int($values[1] ?? null) ? $values[1] : 0;
	}

	/**
	 * A big-endian unsigned number of any length up to 8 bytes.
	 */
	public static function uintbe(string $bytes): int
	{
		$value = 0;

		foreach (str_split($bytes) as $byte) {
			$value = ($value << 8) | ord($byte);
		}

		return $value;
	}

	/**
	 * A size in words people read: `42 KB`.
	 */
	public static function size(int $bytes): string
	{
		return $bytes >= 1_048_576 ? round($bytes / 1_048_576, 1) . ' MB' : max(1, (int) round($bytes / 1024)) . ' KB';
	}
}
