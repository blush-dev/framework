<?php

/**
 * Reflection attribute reader.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Support\Attributes;

use Override;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;

/**
 * Reads attribute instances straight off PHP's reflection API, building fresh
 * instances on every call.
 */
final class ReflectionAttributeReader implements AttributeReader
{
	/**
	 * @inheritDoc
	 *
	 * @template T of object
	 * @param    ReflectionClass<covariant object>|ReflectionMethod|ReflectionProperty|ReflectionClassConstant|ReflectionFunction|ReflectionParameter $target
	 * @param    class-string<T> $attributeClass
	 * @return   list<T>
	 */
	#[Override]
	public function attributesOn(
		ReflectionClass|ReflectionMethod|ReflectionProperty|ReflectionClassConstant|ReflectionFunction|ReflectionParameter $target,
		string $attributeClass,
		int $flags = 0
	): array {
		return array_map(
			static fn (ReflectionAttribute $attribute): object => $attribute->newInstance(),
			$target->getAttributes($attributeClass, $flags)
		);
	}
}
