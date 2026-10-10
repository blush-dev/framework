<?php

/**
 * Secret.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Secret;

use Closure;
use LogicException;
use Override;
use Stringable;

/**
 * A secret's value (D-715), handed over only when asked for by name:
 *
 *     $client->withToken($secret->reveal());
 *
 * Nothing else shows it. Printing it gives `[secret]`, dumping it
 * (`var_dump()`, `print_r()`, an error page) shows no value, and
 * serializing it (a cache, a session) throws. The value is held in a
 * closure rather than a property, so even `var_export()` and an array
 * cast find nothing to show.
 */
final readonly class Secret implements Stringable
{
	/**
	 * What printing a secret gives.
	 */
	public const string MASK = '[secret]';

	/**
	 * Returns the value.
	 *
	 * @var Closure(): string
	 */
	private Closure $value;

	public function __construct(#[\SensitiveParameter] string $value)
	{
		$this->value = static fn (): string => $value;
	}

	/**
	 * Returns the value, for the one place that uses it.
	 */
	public function reveal(): string
	{
		return ($this->value)();
	}

	/**
	 * Whether the value is the one given, compared in constant time.
	 */
	public function equals(#[\SensitiveParameter] string $value): bool
	{
		return hash_equals($this->reveal(), $value);
	}

	/**
	 * Returns `[secret]`, never the value.
	 */
	#[Override]
	public function __toString(): string
	{
		return self::MASK;
	}

	/**
	 * What dumps show: no value.
	 *
	 * @return array{value: string}
	 */
	public function __debugInfo(): array
	{
		return ['value' => self::MASK];
	}

	/**
	 * Refuses: a secret is never serialized.
	 *
	 * @return array<never>
	 * @throws LogicException
	 */
	public function __serialize(): array
	{
		throw new LogicException('A secret can\'t be serialized; keep its name and read it again.');
	}

	/**
	 * Refuses: a secret is never unserialized.
	 *
	 * @param  array<mixed> $data
	 * @throws LogicException
	 */
	public function __unserialize(array $data): void
	{
		throw new LogicException('A secret can\'t be unserialized.');
	}
}
