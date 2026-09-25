<?php

/**
 * Field definition data.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Content\Schema;

/**
 * Typed access to a field or schema definition array (from a data-defined
 * content type or a compiled cache), throwing `InvalidSchema` with the
 * field's name when a value has the wrong type.
 */
final readonly class Definition
{
	/**
	 * @param array<array-key, mixed> $data
	 */
	public function __construct(private array $data, private string $context)
	{}

	/**
	 * Returns whether a key is present.
	 */
	public function has(string $key): bool
	{
		return array_key_exists($key, $this->data);
	}

	/**
	 * Returns a raw value.
	 */
	public function raw(string $key, mixed $default = null): mixed
	{
		return $this->data[$key] ?? $default;
	}

	/**
	 * @throws InvalidSchema
	 */
	public function string(string $key, string $default = ''): string
	{
		$value = $this->data[$key] ?? $default;

		return is_string($value) ? $value : throw $this->invalid($key, 'a string');
	}

	/**
	 * @throws InvalidSchema
	 */
	public function nullableString(string $key): ?string
	{
		$value = $this->data[$key] ?? null;

		return $value === null || is_string($value) ? $value : throw $this->invalid($key, 'a string');
	}

	/**
	 * @throws InvalidSchema
	 */
	public function bool(string $key, bool $default = false): bool
	{
		$value = $this->data[$key] ?? $default;

		return is_bool($value) ? $value : throw $this->invalid($key, 'true or false');
	}

	/**
	 * @throws InvalidSchema
	 */
	public function number(string $key): int|float|null
	{
		$value = $this->data[$key] ?? null;

		return $value === null || is_int($value) || is_float($value) ? $value : throw $this->invalid($key, 'a number');
	}

	/**
	 * Returns a list of strings; a single string counts as a list of one.
	 *
	 * @return list<string>
	 * @throws InvalidSchema
	 */
	public function strings(string $key): array
	{
		$value = $this->data[$key] ?? [];
		$value = is_string($value) ? [$value] : $value;

		if (! is_array($value) || ! array_is_list($value) || ! array_all($value, static fn (mixed $item): bool => is_string($item))) {
			throw $this->invalid($key, 'a list of strings');
		}

		/** @var list<string> $value */
		return $value;
	}

	/**
	 * Returns a nested map.
	 *
	 * @return array<array-key, mixed>
	 * @throws InvalidSchema
	 */
	public function map(string $key): array
	{
		$value = $this->data[$key] ?? [];

		return is_array($value) && ($value === [] || ! array_is_list($value)) ? $value : throw $this->invalid($key, 'a map');
	}

	/**
	 * Returns a list of maps.
	 *
	 * @return list<array<array-key, mixed>>
	 * @throws InvalidSchema
	 */
	public function maps(string $key): array
	{
		$value = $this->data[$key] ?? [];

		if (! is_array($value) || ! array_is_list($value) || ! array_all($value, static fn (mixed $item): bool => is_array($item))) {
			throw $this->invalid($key, 'a list of maps');
		}

		/** @var list<array<array-key, mixed>> $value */
		return $value;
	}

	/**
	 * Builds the exception for a value of the wrong type.
	 */
	private function invalid(string $key, string $expected): InvalidSchema
	{
		return new InvalidSchema(sprintf('%s "%s" must be %s.', $this->context, $key, $expected));
	}
}
