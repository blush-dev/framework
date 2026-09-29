<?php

/**
 * Session.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Session;

use InvalidArgumentException;

/**
 * One visitor's session, for the length of a request. `StartSession` loads
 * it (or starts one), puts it on the request as the `Session::class`
 * attribute, and saves it after the response.
 *
 * Unlike most of Blush, it's mutable: a login or logout changes the
 * session the middleware saves, the way PHP's own sessions do, without
 * superglobals or `session_*()` (D-219). Values must be JSON data.
 *
 * The id is 32 random bytes as hex. `regenerate()` gives it a new one (at
 * login, against session fixation), and `invalidate()` ends it.
 */
final class Session
{
	/**
	 * The id before `regenerate()`, to delete.
	 */
	private ?string $previousId = null;

	/**
	 * Whether the data changed.
	 */
	private bool $dirty = false;

	/**
	 * Whether the session was ended.
	 */
	private bool $invalidated = false;

	/**
	 * @param array<string, mixed> $data
	 */
	public function __construct(
		private string $id,
		private array $data,
		public readonly int $created,
		private bool $new = false
	) {}

	/**
	 * Starts a new, empty session.
	 */
	public static function start(int $now): self
	{
		return new self(self::newId(), [], $now, new: true);
	}

	/**
	 * Returns a new random id.
	 */
	public static function newId(): string
	{
		return bin2hex(random_bytes(32));
	}

	/**
	 * Whether a string has the shape of a session id.
	 */
	public static function isValidId(string $id): bool
	{
		return preg_match('/^[0-9a-f]{64}$/', $id) === 1;
	}

	/**
	 * Returns the id.
	 */
	public function id(): string
	{
		return $this->id;
	}

	/**
	 * Returns the id before `regenerate()`, if it was called.
	 */
	public function previousId(): ?string
	{
		return $this->previousId;
	}

	/**
	 * Returns a value.
	 */
	public function get(string $key, mixed $default = null): mixed
	{
		return $this->data[$key] ?? $default;
	}

	/**
	 * Whether a value is set.
	 */
	public function has(string $key): bool
	{
		return isset($this->data[$key]);
	}

	/**
	 * Sets a value.
	 *
	 * @throws InvalidArgumentException When the value isn't JSON data.
	 */
	public function set(string $key, mixed $value): void
	{
		if (! self::isData($value)) {
			throw new InvalidArgumentException(sprintf('Session value "%s" must be JSON data (scalars, null, and arrays of them).', $key));
		}

		$this->data[$key] = $value;
		$this->dirty      = true;
	}

	/**
	 * Removes a value.
	 */
	public function remove(string $key): void
	{
		if (array_key_exists($key, $this->data)) {
			unset($this->data[$key]);
			$this->dirty = true;
		}
	}

	/**
	 * Returns every value.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array
	{
		return $this->data;
	}

	/**
	 * Gives the session a new id, keeping its data. The old id is deleted
	 * when the session is saved.
	 */
	public function regenerate(): void
	{
		$this->previousId ??= $this->id;
		$this->id           = self::newId();
		$this->dirty        = true;
	}

	/**
	 * Ends the session: its data is dropped, its record deleted, and its
	 * cookie expired.
	 */
	public function invalidate(): void
	{
		$this->data        = [];
		$this->invalidated = true;
	}

	/**
	 * Whether the session started with this request.
	 */
	public function isNew(): bool
	{
		return $this->new;
	}

	/**
	 * Whether the data or id changed.
	 */
	public function isDirty(): bool
	{
		return $this->dirty;
	}

	/**
	 * Whether the session was ended.
	 */
	public function isInvalidated(): bool
	{
		return $this->invalidated;
	}

	/**
	 * Whether a value can be stored as JSON.
	 */
	private static function isData(mixed $value): bool
	{
		return is_scalar($value) || $value === null || (is_array($value) && array_all($value, static fn (mixed $item): bool => self::isData($item)));
	}
}
