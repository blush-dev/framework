<?php

/**
 * Config values reader.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Config;

use BackedEnum;

/**
 * Typed reads from an untyped config array, for `Config::fromArray()`
 * implementations. Each accessor returns the value or the default, and throws
 * `InvalidConfig` naming the config and key when a value has the wrong type.
 * `assertKnownKeys()` catches typos.
 */
final readonly class ConfigValues
{
	/**
	 * @param array<array-key, mixed> $data
	 * @param string                  $config Names the config in error messages.
	 */
	public function __construct(
		private array $data,
		private string $config
	) {
	}

	/**
	 * Throws when the data holds a key outside `$known`.
	 *
	 * @param  list<string> $known
	 * @throws InvalidConfig
	 */
	public function assertKnownKeys(array $known): void
	{
		$unknown = array_diff(array_map(strval(...), array_keys($this->data)), $known);

		if ($unknown !== []) {
			throw new InvalidConfig(sprintf(
				'%s has unknown key(s): %s.',
				$this->config,
				implode(', ', $unknown)
			));
		}
	}

	/**
	 * Whether the key is present (even if `null`).
	 */
	public function has(string $key): bool
	{
		return array_key_exists($key, $this->data);
	}

	/**
	 * @throws InvalidConfig
	 */
	public function string(string $key, string $default): string
	{
		$value = $this->data[$key] ?? $default;

		return is_string($value) ? $value : throw $this->invalid($key, 'a string', $value);
	}

	/**
	 * @throws InvalidConfig
	 */
	public function nullableString(string $key, ?string $default = null): ?string
	{
		$value = $this->has($key) ? $this->data[$key] : $default;

		return $value === null || is_string($value) ? $value : throw $this->invalid($key, 'a string or null', $value);
	}

	/**
	 * @throws InvalidConfig
	 */
	public function bool(string $key, bool $default): bool
	{
		$value = $this->data[$key] ?? $default;

		return is_bool($value) ? $value : throw $this->invalid($key, 'a boolean', $value);
	}

	/**
	 * @throws InvalidConfig
	 */
	public function int(string $key, int $default): int
	{
		$value = $this->data[$key] ?? $default;

		return is_int($value) ? $value : throw $this->invalid($key, 'an integer', $value);
	}

	/**
	 * Returns a list of strings.
	 *
	 * @param  list<string> $default
	 * @return list<string>
	 * @throws InvalidConfig
	 */
	public function stringList(string $key, array $default = []): array
	{
		$value = $this->data[$key] ?? $default;

		if (! is_array($value) || ! array_is_list($value)) {
			throw $this->invalid($key, 'a list of strings', $value);
		}

		$list = [];

		foreach ($value as $item) {
			$list[] = is_string($item) ? $item : throw $this->invalid($key, 'a list of strings', $value);
		}

		return $list;
	}

	/**
	 * Returns a nullable list of strings; `null` stays `null`.
	 *
	 * @param  ?list<string> $default
	 * @return ?list<string>
	 * @throws InvalidConfig
	 */
	public function nullableStringList(string $key, ?array $default = null): ?array
	{
		$value = $this->has($key) ? $this->data[$key] : $default;

		return $value === null ? null : new self([$key => $value], $this->config)->stringList($key);
	}

	/**
	 * Returns a backed enum case, accepting either the case or its value.
	 *
	 * @template T of BackedEnum
	 * @param    class-string<T> $enum
	 * @param    T               $default
	 * @return   T
	 * @throws   InvalidConfig
	 */
	public function enum(string $key, string $enum, BackedEnum $default): BackedEnum
	{
		$value = $this->data[$key] ?? $default;

		if ($value instanceof $enum) {
			return $value;
		}

		$case = is_int($value) || is_string($value) ? $enum::tryFrom($value) : null;

		return $case ?? throw $this->invalid($key, sprintf('one of: %s', implode(', ', array_map(
			static fn (BackedEnum $case): string => (string) $case->value,
			$enum::cases()
		))), $value);
	}

	/**
	 * Builds an invalid-value exception.
	 */
	private function invalid(string $key, string $expected, mixed $value): InvalidConfig
	{
		return new InvalidConfig(sprintf(
			'%s "%s" must be %s; %s given.',
			$this->config,
			$key,
			$expected,
			get_debug_type($value)
		));
	}
}
