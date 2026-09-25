<?php

/**
 * Reflection planner.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Container\Plan;

use Override;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionFunctionAbstract;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use UnitEnum;
use Blush\Container\Attributes\ContextualAttribute;
use Blush\Container\Attributes\NoAutowire;
use Blush\Container\Attributes\Singleton;
use Blush\Container\Attributes\SingletonWhen;
use Blush\Container\Attributes\Tag;

/**
 * Builds resolution plans from reflection, reflecting each class once per
 * instance. Every plan it has built stays available through `plans()`, so a
 * warmed-up container can hand them to `PlanCache` for compiling.
 */
final class ReflectionPlanner implements Planner
{
	/**
	 * Plans built so far, keyed by class name.
	 *
	 * @var array<class-string, ClassPlan>
	 */
	private array $plans = [];

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function forClass(string $class): ClassPlan
	{
		return $this->plans[$class] ??= $this->plan(new ReflectionClass($class));
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function forFunction(ReflectionFunctionAbstract $function): array
	{
		return array_map($this->planParameter(...), $function->getParameters());
	}

	/**
	 * Returns every class plan built so far.
	 *
	 * @return array<class-string, ClassPlan>
	 */
	public function plans(): array
	{
		return $this->plans;
	}

	/**
	 * Builds the plan for a reflected class.
	 *
	 * @param ReflectionClass<object> $class
	 */
	private function plan(ReflectionClass $class): ClassPlan
	{
		$constructor = $class->getConstructor();

		$singletonWhen = array_first($class->getAttributes(SingletonWhen::class));

		return new ClassPlan(
			class: $class->getName(),
			instantiable: $class->isInstantiable(),
			parameters: $constructor === null ? null : $this->forFunction($constructor),
			singleton: $class->getAttributes(Singleton::class) !== [],
			singletonWhen: $singletonWhen === null ? null : AttributeSpec::fromReflection($singletonWhen),
			tags: array_map(
				AttributeSpec::fromReflection(...),
				$class->getAttributes(Tag::class)
			)
		);
	}

	/**
	 * Builds the plan for a reflected parameter.
	 */
	private function planParameter(ReflectionParameter $param): ParameterPlan
	{
		$type = $param->getType();

		$hasDefault = $param->isDefaultValueAvailable();
		$default    = $hasDefault ? $param->getDefaultValue() : null;

		// An object default (`new` in an initializer) must be built
		// fresh each time, so it's evaluated through reflection on
		// demand rather than stored. Enum cases are objects too, but
		// they're singletons and export cleanly, so they're stored.
		$defaultFactory = is_object($default) && ! $default instanceof UnitEnum
			? static fn (): mixed => $param->getDefaultValue()
			: null;

		$contextual = array_first($param->getAttributes(
			ContextualAttribute::class,
			ReflectionAttribute::IS_INSTANCEOF
		));

		return new ParameterPlan(
			name: $param->getName(),
			position: $param->getPosition(),
			alternatives: $this->typeAlternatives($type),
			typeName: $type instanceof ReflectionNamedType && ! $type->isBuiltin() ? $type->getName() : null,
			typeString: $type === null ? null : (string) $type,
			variadic: $param->isVariadic(),
			nullable: $type?->allowsNull() ?? false,
			hasDefault: $hasDefault,
			default: $defaultFactory === null ? $default : null,
			noAutowire: $param->getAttributes(NoAutowire::class) !== [],
			contextual: $contextual === null ? null : AttributeSpec::fromReflection($contextual),
			defaultFactory: $defaultFactory
		);
	}

	/**
	 * Decomposes a parameter type into the alternatives that can satisfy
	 * it, in declaration order: one alternative per union member, each a
	 * list of the class names a single object must satisfy together. This
	 * mirrors the disjunctive normal form PHP uses for composite types,
	 * such as `(A&B)|C`. Built-in types yield nothing.
	 *
	 * @return list<list<class-string>>
	 */
	private function typeAlternatives(?ReflectionType $type): array
	{
		if ($type instanceof ReflectionNamedType) {
			return $type->isBuiltin() ? [] : [[$this->className($type)]];
		}

		if ($type instanceof ReflectionIntersectionType) {
			return [$this->intersectionMembers($type)];
		}

		if (! $type instanceof ReflectionUnionType) {
			return [];
		}

		$alternatives = [];

		foreach ($type->getTypes() as $member) {
			if ($member instanceof ReflectionIntersectionType) {
				$alternatives[] = $this->intersectionMembers($member);
			} elseif (! $member->isBuiltin()) {
				$alternatives[] = [$this->className($member)];
			}
		}

		return $alternatives;
	}

	/**
	 * Returns the class names that make up an intersection type, narrowed
	 * to named types (the only members PHP permits in an intersection).
	 *
	 * @return list<class-string>
	 */
	private function intersectionMembers(ReflectionIntersectionType $type): array
	{
		$members = [];

		foreach ($type->getTypes() as $member) {
			if ($member instanceof ReflectionNamedType) {
				$members[] = $this->className($member);
			}
		}

		return $members;
	}

	/**
	 * Returns a non-builtin named type's class name. `self` and `static`
	 * are left as-is; they never name a buildable dependency anyway.
	 *
	 * @return class-string
	 */
	private function className(ReflectionNamedType $type): string
	{
		/** @var class-string */
		return $type->getName();
	}
}
