<?php

/**
 * Attribute reader interface.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Support\Attributes;

use ReflectionClass;
use ReflectionClassConstant;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;

/**
 * Reads attribute instances declared on a reflected class, method, property,
 * constant, function, or parameter. `ReflectionAttributeReader` reads them
 * fresh each time; `CachedAttributeReader` decorates a reader to reuse
 * results.
 *
 * @phpstan-type Target ReflectionClass<covariant object>|ReflectionMethod|ReflectionProperty|ReflectionClassConstant|ReflectionFunction|ReflectionParameter
 */
interface AttributeReader
{
	/**
	 * Returns every instance of `$attributeClass` declared on `$target`.
	 * `$flags` mirrors `ReflectionClass::getAttributes()`; pass
	 * `ReflectionAttribute::IS_INSTANCEOF` to match subclasses instead of
	 * requiring the exact class.
	 *
	 * @template T of object
	 * @param    Target          $target
	 * @param    class-string<T> $attributeClass
	 * @return   list<T>
	 */
	public function attributesOn(
		ReflectionClass|ReflectionMethod|ReflectionProperty|ReflectionClassConstant|ReflectionFunction|ReflectionParameter $target,
		string $attributeClass,
		int $flags = 0
	): array;
}
