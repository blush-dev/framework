<?php

/**
 * Cached attribute reader.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Support\Attributes;

use Override;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;

/**
 * Decorates a reader to reuse each result, keyed by target, attribute class,
 * and flags, so a repeated read of the same combination returns the same
 * instances instead of rebuilding them. The cache lives for the life of the
 * reader; attribute instances can hold closures (PHP 8.5), so they aren't
 * persisted across requests.
 *
 * @phpstan-import-type Target from AttributeReader
 */
final class CachedAttributeReader implements AttributeReader
{
	/**
	 * Cached results, keyed by target, attribute class, and flags.
	 *
	 * @var array<string, list<object>>
	 */
	private array $cache = [];

	public function __construct(
		private readonly AttributeReader $reader = new ReflectionAttributeReader()
	) {
	}

	/**
	 * @inheritDoc
	 *
	 * @template T of object
	 * @param    Target          $target
	 * @param    class-string<T> $attributeClass
	 * @return   list<T>
	 */
	#[Override]
	public function attributesOn(
		ReflectionClass|ReflectionMethod|ReflectionProperty|ReflectionClassConstant|ReflectionFunction|ReflectionParameter $target,
		string $attributeClass,
		int $flags = 0
	): array {
		$key = "{$this->identify($target)}|{$attributeClass}|{$flags}";

		$this->cache[$key] ??= $this->reader->attributesOn($target, $attributeClass, $flags);

		// Every cached instance matches by construction; filtering keeps
		// the element type honest for callers.
		return array_values(array_filter(
			$this->cache[$key],
			static fn (object $attribute): bool => $attribute instanceof $attributeClass
		));
	}

	/**
	 * A stable string identity for a reflected member.
	 *
	 * @param Target $target
	 */
	private function identify(
		ReflectionClass|ReflectionMethod|ReflectionProperty|ReflectionClassConstant|ReflectionFunction|ReflectionParameter $target
	): string {
		return match (true) {
			$target instanceof ReflectionClass => $target->getName(),
			$target instanceof ReflectionFunction => $target->isClosure()
				? sprintf('{closure}@%s:%d', (string) $target->getFileName(), (int) $target->getStartLine())
				: $target->getName(),
			$target instanceof ReflectionParameter => sprintf(
				'%s($%s:%d)',
				$this->identifyFunction($target->getDeclaringFunction()),
				$target->getName(),
				$target->getPosition()
			),
			$target instanceof ReflectionClassConstant => "{$target->class}::{$target->getName()}",
			default => "{$target->class}::" . ($target instanceof ReflectionProperty ? '$' : '') . $target->getName()
		};
	}

	/**
	 * A stable string identity for a parameter's declaring function.
	 */
	private function identifyFunction(ReflectionFunctionAbstract $function): string
	{
		return $function instanceof ReflectionMethod
			? "{$function->class}::{$function->getName()}"
			: $function->getName();
	}
}
