<?php

/**
 * Env.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Env;

use BackedEnum;

/**
 * Read-only access to environment variables, with typed accessors. Values come
 * from the process environment and, optionally, a `.env` file; the process
 * environment wins, so a host can override a committed `.env`. Nothing is
 * written back to the environment (no `putenv()`), so loading `.env` has no
 * global side effects.
 *
 * Config files are the only place most code should read env from: they turn
 * env values into typed config objects (D-017).
 */
final readonly class Env
{
	/**
	 * @param array<string, string> $values
	 */
	public function __construct(private array $values = [])
	{
	}

	/**
	 * Loads a `.env` file (if it exists) under the given process
	 * environment. Pass `getenv()` for the real environment.
	 *
	 * @param  array<string, string> $environment
	 * @throws EnvException When the file can't be read or parsed.
	 */
	public static function load(string $file, array $environment = []): self
	{
		if (! is_file($file)) {
			return new self($environment);
		}

		$contents = @file_get_contents($file);

		if ($contents === false) {
			throw new EnvException(sprintf('Unable to read "%s".', $file));
		}

		try {
			$values = new EnvParser()->parse($contents, $environment);
		} catch (EnvException $e) {
			throw new EnvException(sprintf('%s (%s)', $e->getMessage(), $file), previous: $e);
		}

		return new self([...$values, ...$environment]);
	}

	/**
	 * Whether the variable is set (even to an empty string).
	 */
	public function has(string $name): bool
	{
		return array_key_exists($name, $this->values);
	}

	/**
	 * Returns the raw value, or `$default` when unset.
	 */
	public function get(string $name, ?string $default = null): ?string
	{
		return $this->values[$name] ?? $default;
	}

	/**
	 * Returns every variable.
	 *
	 * @return array<string, string>
	 */
	public function all(): array
	{
		return $this->values;
	}

	/**
	 * Returns a string. Throws when the variable is unset and no default is
	 * given.
	 *
	 * @throws EnvException
	 */
	public function string(string $name, ?string $default = null): string
	{
		return $this->values[$name] ?? $default ?? throw $this->missing($name);
	}

	/**
	 * Returns a boolean. Accepts `true`/`false`, `1`/`0`, `yes`/`no`, and
	 * `on`/`off` (any case); an empty value is `false`.
	 *
	 * @throws EnvException
	 */
	public function bool(string $name, ?bool $default = null): bool
	{
		if (! $this->has($name)) {
			return $default ?? throw $this->missing($name);
		}

		return match (strtolower(trim($this->values[$name]))) {
			'1', 'true', 'yes', 'on'      => true,
			'0', 'false', 'no', 'off', '' => false,
			default => throw $this->invalid($name, 'a boolean')
		};
	}

	/**
	 * Returns an integer.
	 *
	 * @throws EnvException
	 */
	public function int(string $name, ?int $default = null): int
	{
		if (! $this->has($name)) {
			return $default ?? throw $this->missing($name);
		}

		$value = filter_var(trim($this->values[$name]), FILTER_VALIDATE_INT);

		return is_int($value) ? $value : throw $this->invalid($name, 'an integer');
	}

	/**
	 * Returns a float.
	 *
	 * @throws EnvException
	 */
	public function float(string $name, ?float $default = null): float
	{
		if (! $this->has($name)) {
			return $default ?? throw $this->missing($name);
		}

		$value = filter_var(trim($this->values[$name]), FILTER_VALIDATE_FLOAT);

		return is_float($value) ? $value : throw $this->invalid($name, 'a number');
	}

	/**
	 * Returns a list, split on `$separator` with each item trimmed and
	 * empty items dropped.
	 *
	 * @param  ?list<string>    $default
	 * @return list<string>
	 * @throws EnvException
	 */
	public function list(string $name, ?array $default = null, string $separator = ','): array
	{
		if (! $this->has($name)) {
			return $default ?? throw $this->missing($name);
		}

		return array_values(array_filter(
			array_map(trim(...), explode($separator === '' ? ',' : $separator, $this->values[$name])),
			static fn (string $item): bool => $item !== ''
		));
	}

	/**
	 * Returns a backed enum case from its value.
	 *
	 * @template T of BackedEnum
	 * @param    class-string<T> $enum
	 * @param    ?T              $default
	 * @return   T
	 * @throws   EnvException
	 */
	public function enum(string $name, string $enum, ?BackedEnum $default = null): BackedEnum
	{
		if (! $this->has($name)) {
			return $default ?? throw $this->missing($name);
		}

		$value = trim($this->values[$name]);
		$case  = $enum::tryFrom(ctype_digit($value) ? (int) $value : $value);

		return $case ?? throw $this->invalid($name, sprintf('one of: %s', implode(', ', array_map(
			static fn (BackedEnum $case): string => (string) $case->value,
			$enum::cases()
		))));
	}

	/**
	 * Builds a missing-variable exception.
	 */
	private function missing(string $name): EnvException
	{
		return new EnvException(sprintf('Environment variable "%s" is not set.', $name));
	}

	/**
	 * Builds an invalid-value exception. The value itself is left out of
	 * the message, since env often holds secrets.
	 */
	private function invalid(string $name, string $expected): EnvException
	{
		return new EnvException(sprintf('Environment variable "%s" must be %s.', $name, $expected));
	}
}
