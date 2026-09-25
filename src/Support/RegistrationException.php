<?php

/**
 * Registration exception.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Support;

use LogicException;
use Blush\Core\BlushException;

/**
 * Thrown when a class cannot be registered with a registry, either because it
 * doesn't satisfy the registry's contract or because it isn't instantiable,
 * or when the registry itself declares no valid contract.
 */
final class RegistrationException extends LogicException implements BlushException
{
	/**
	 * A registry that declares no contract.
	 */
	public static function missingContract(string $registry): self
	{
		return new self(sprintf(
			'Cannot use the %s registry; it must define a CONTRACT constant or override the contract() method.',
			$registry
		));
	}

	/**
	 * A registry whose contract is not an existing class or interface.
	 */
	public static function invalidContract(string $registry, string $contract): self
	{
		return new self(sprintf(
			'Cannot use the %1$s registry; its contract %2$s is not a class or interface.',
			$registry,
			$contract
		));
	}

	/**
	 * A class that doesn't satisfy the contract.
	 */
	public static function notSubclassOf(string $given, string $expected): self
	{
		return new self(sprintf(
			'Cannot register %1$s; only %2$s subclasses are allowed.',
			$given,
			$expected
		));
	}

	/**
	 * A class that cannot be instantiated, such as an abstract class or one
	 * with a non-public constructor.
	 */
	public static function notInstantiable(string $given): self
	{
		return new self(sprintf(
			'Cannot register %s; the class is not instantiable.',
			$given
		));
	}
}
