<?php

/**
 * Container implementation.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Container;

use Closure;
use Error;
use Override;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use UnitEnum;
use WeakMap;
use Blush\Container\Plan\AttributeSpec;
use Blush\Container\Plan\ClassPlan;
use Blush\Container\Plan\ParameterPlan;
use Blush\Container\Plan\Planner;
use Blush\Container\Plan\ReflectionPlanner;

/**
 * Implementation of the dependency injection container.
 *
 * Classes are built from resolution plans (see `Plan\Planner`) rather than by
 * reflecting on every build. By default, plans come from reflection and are
 * kept for the life of the container. In production, pass a planner from
 * `Plan\PlanCache` so plans are read from a compiled PHP file (D-044).
 *
 * @phpstan-import-type Condition from Container
 */
final class ServiceContainer implements Container
{
	/**
	 * Stores registered services.
	 *
	 * @var array<string, array{concrete: mixed, shared: bool}>
	 */
	private array $bindings = [];

	/**
	 * Stores registered instances and resolved singletons.
	 *
	 * @var array<string, mixed>
	 */
	private array $instances = [];

	/**
	 * Maps tag names to the list of abstracts assigned to them.
	 *
	 * @var array<string, list<string>>
	 */
	private array $tags = [];

	/**
	 * Maps a tag name and abstract to the attributes it was tagged with.
	 *
	 * @var array<string, array<string, array<string, mixed>>>
	 */
	private array $tagAttributes = [];

	/**
	 * Maps a tag name to the abstract every member must be a concrete class
	 * of, set via `setTagContract()`. Tags without a contract are absent
	 * from this map.
	 *
	 * @var array<string, class-string>
	 */
	private array $tagContracts = [];

	/**
	 * Maps an alias to the abstract it points at. Aliases are followed
	 * transitively when an identifier is resolved.
	 *
	 * @var array<string, string>
	 */
	private array $aliases = [];

	/**
	 * Tracks the abstracts currently being resolved so that circular
	 * dependencies are detected instead of recursing into a stack overflow.
	 *
	 * @var list<string>
	 */
	private array $buildStack = [];

	/**
	 * Memoizes attribute instances built from plan specs, so an attribute
	 * is only instantiated once per plan.
	 *
	 * @var WeakMap<AttributeSpec<object>, object>
	 */
	private WeakMap $attributeInstances;

	/**
	 * Maps an abstract to the list of callbacks to run after it is built.
	 *
	 * @var array<string, list<Closure(object, ServiceResolver): void>>
	 */
	private array $resolvingCallbacks = [];

	/**
	 * Maps an abstract to the list of decorators applied after it is built.
	 *
	 * @var array<string, list<Closure(object, ServiceResolver): object>>
	 */
	private array $decorators = [];

	/**
	 * Contextual bindings, keyed by the concrete class being built. Each
	 * consumer holds two buckets: `params` maps a constructor parameter
	 * name to a literal value (or a closure computing one), and `types`
	 * maps a parameter's type to a class-string the container resolves (or
	 * a closure). Keeping the two kinds separate is what lets resolution
	 * interpret each without parsing or guessing whether a value is a
	 * literal or a class.
	 *
	 * @var array<string, array{params?: array<string, mixed>, types?: array<string, Closure|string>}>
	 */
	private array $contextual = [];

	/**
	 * Stores named parameter values available to any consumer parameter
	 * marked with `#[Param]`.
	 *
	 * @var array<string, array<array-key, mixed>|bool|string|int|float|UnitEnum|null>
	 */
	private array $params = [];

	/**
	 * Accepts the planner that supplies resolution plans.
	 */
	public function __construct(private readonly Planner $planner = new ReflectionPlanner())
	{
		$this->attributeInstances = new WeakMap();
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function singleton(string $abstract, mixed $concrete = null): void
	{
		unset($this->instances[$abstract], $this->aliases[$abstract]);

		$this->bindings[$abstract] = [
			'concrete' => $concrete ?? $abstract,
			'shared'   => true
		];
	}

	/**
	 * @inheritDoc
	 * @throws ContainerException
	 */
	#[Override]
	public function singletonIf(string $abstract, mixed $concrete = null): void
	{
		if (! $this->registered($abstract)) {
			$this->singleton($abstract, $concrete);
		}
	}

	/**
	 * @inheritDoc
	 * @param  Condition $condition
	 * @throws ContainerException
	 */
	#[Override]
	public function singletonWhen(
		string $abstract,
		Closure|string|array|bool $condition,
		mixed $concrete = null
	): void {
		if ($this->conditionIsTrue($condition)) {
			$this->singleton($abstract, $concrete);
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function transient(string $abstract, mixed $concrete = null): void
	{
		unset($this->instances[$abstract], $this->aliases[$abstract]);

		$this->bindings[$abstract] = [
			'concrete' => $concrete ?? $abstract,
			'shared'   => false
		];
	}

	/**
	 * @inheritDoc
	 * @throws ContainerException
	 */
	#[Override]
	public function transientIf(string $abstract, mixed $concrete = null): void
	{
		if (! $this->registered($abstract)) {
			$this->transient($abstract, $concrete);
		}
	}

	/**
	 * @inheritDoc
	 * @param  Condition $condition
	 * @throws ContainerException
	 */
	#[Override]
	public function transientWhen(
		string $abstract,
		Closure|string|array|bool $condition,
		mixed $concrete = null
	): void {
		if ($this->conditionIsTrue($condition)) {
			$this->transient($abstract, $concrete);
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function instance(string $abstract, mixed $instance): void
	{
		unset($this->aliases[$abstract]);

		$this->instances[$abstract] = $instance;
	}

	/**
	 * @inheritDoc
	 * @throws ContainerException
	 */
	#[Override]
	public function alias(string $alias, string $abstract): void
	{
		if ($alias === $abstract) {
			throw new ContainerException(sprintf('Cannot alias "%s" to itself.', $alias));
		}

		// An identifier is either an alias or a binding, never both, so a
		// new alias drops any binding or cached instance under that name.
		unset($this->bindings[$alias], $this->instances[$alias]);

		$this->aliases[$alias] = $abstract;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function whenNeedsParam(string $consumer, string $param, mixed $value): void
	{
		$this->contextual[$consumer]['params'][$param] = $value;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function whenNeedsType(string $consumer, string $type, Closure|string $concrete): void
	{
		$this->contextual[$consumer]['types'][$type] = $concrete;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function setParam(string $parameter, array|bool|string|int|float|UnitEnum|null $value): void
	{
		$this->params[$parameter] = $value;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function getParam(string $parameter): array|bool|string|int|float|UnitEnum|null
	{
		if (! $this->hasParam($parameter)) {
			throw new NotFoundException(sprintf('Parameter "%s" is not set.', $parameter));
		}

		return $this->params[$parameter];
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function hasParam(string $parameter): bool
	{
		return array_key_exists($parameter, $this->params);
	}

	/**
	 * @inheritDoc
	 * @throws ContainerException
	 */
	#[Override]
	public function get(string $abstract): mixed
	{
		return $this->resolve($abstract);
	}

	/**
	 * @inheritDoc
	 * @throws ContainerException
	 */
	#[Override]
	public function make(string $abstract, array $parameters = []): object
	{
		return $this->assertInstanceOf($abstract, $this->resolveObject($abstract, $parameters));
	}

	/**
	 * @inheritDoc
	 * @throws ContainerException
	 */
	#[Override]
	public function build(string $abstract, array $parameters = []): object
	{
		return $this->assertInstanceOf($abstract, $this->resolveObject($abstract, $parameters, fresh: true));
	}

	/**
	 * @inheritDoc
	 * @throws ContainerException
	 */
	#[Override]
	public function call(callable|array $callback, array $parameters = []): mixed
	{
		try {
			[$reflector, $invokable] = $this->reflectCallback($callback);
		} catch (ReflectionException $e) {
			throw new ContainerException(
				sprintf('Cannot call the given callback: %s', $e->getMessage()),
				previous: $e
			);
		}

		$args = $this->resolveDependencies(
			$this->planner->forFunction($reflector),
			$parameters,
			null,
			$this->describeFunction($reflector)
		);

		return $invokable(...$args);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function defer(string $abstract): Closure
	{
		return fn (array $parameters = []): object => $this->resolveObject(
			$abstract,
			$this->namedParameters($parameters)
		);
	}

	/**
	 * @inheritDoc
	 * @throws ContainerException
	 */
	#[Override]
	public function resolving(string $abstract, Closure $callback): void
	{
		$this->resolvingCallbacks[$this->getAlias($abstract)][] = $callback;
	}

	/**
	 * @inheritDoc
	 * @throws ContainerException
	 */
	#[Override]
	public function decorate(string $abstract, Closure $closure): void
	{
		$abstract = $this->getAlias($abstract);

		$this->decorators[$abstract][] = $closure;

		// If the instance is already cached (a resolved singleton, or an
		// object bound via instance()), decorate it now and replace the
		// stored copy so later resolutions return the wrapped object.
		if (isset($this->instances[$abstract]) && is_object($this->instances[$abstract])) {
			$this->instances[$abstract] = $closure($this->instances[$abstract], $this);
		}
	}

	/**
	 * @inheritDoc
	 * @throws ContainerException
	 */
	#[Override]
	public function has(string $abstract): bool
	{
		$abstract = $this->getAlias($abstract);

		// Mirror resolve(): a not-found error is avoided when the
		// abstract is registered or its concrete is buildable (an
		// existing class).
		return $this->registered($abstract) || $this->isBuildable($abstract);
	}

	/**
	 * @inheritDoc
	 * @throws ContainerException
	 */
	#[Override]
	public function registered(string $abstract): bool
	{
		$abstract = $this->getAlias($abstract);

		return isset($this->bindings[$abstract]) || array_key_exists($abstract, $this->instances);
	}

	/**
	 * @inheritDoc
	 * @throws ContainerException
	 */
	#[Override]
	public function resolved(string $abstract): bool
	{
		return array_key_exists($this->getAlias($abstract), $this->instances);
	}

	/**
	 * @inheritDoc
	 * @throws ContainerException
	 */
	#[Override]
	public function forgetInstance(string $abstract): void
	{
		unset($this->instances[$this->getAlias($abstract)]);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function tag(string|array $abstracts, string $tag, array $attributes = []): void
	{
		$contract = $this->tagContracts[$tag] ?? null;

		foreach ((array) $abstracts as $abstract) {
			// A typed tag holds concrete implementations of its
			// contract, so each new member is validated as it is
			// added rather than deferred to resolution.
			if ($contract !== null) {
				$this->assertTagIsConcreteOf($tag, $abstract, $contract);
			}

			if (! in_array($abstract, $this->tags[$tag] ?? [], true)) {
				$this->tags[$tag][] = $abstract;
			}

			if ($attributes !== []) {
				$this->tagAttributes[$tag][$abstract] = $attributes;
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function tagFromAttributes(string $class): void
	{
		if (! class_exists($class)) {
			throw new ContainerException(sprintf('Cannot read tags from "%s"; it is not a class.', $class));
		}

		foreach ($this->plan($class)->tags as $spec) {
			$tag = $this->attributeInstance($spec);
			$this->tag($class, $tag->tag(), $tag->attributes());
		}
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function setTagContract(string $tag, string $contract): void
	{
		// The contract is set once. A later call naming a different
		// contract for the same tag is a conflict, not a silent override.
		if (isset($this->tagContracts[$tag]) && $this->tagContracts[$tag] !== $contract) {
			throw new ContainerException(sprintf(
				'Tag "%s" is already typed as "%s"; cannot retype it as "%s".',
				$tag,
				$this->tagContracts[$tag],
				$contract
			));
		}

		$this->tagContracts[$tag] = $contract;

		// Validate members tagged before the contract was declared, so a
		// mistake surfaces here rather than waiting until resolution.
		$this->assertTagContract($tag);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function untag(string|array $abstracts, string $tag): void
	{
		if (! isset($this->tags[$tag])) {
			return;
		}

		$this->tags[$tag] = array_values(array_diff($this->tags[$tag], (array) $abstracts));

		foreach ((array) $abstracts as $abstract) {
			unset($this->tagAttributes[$tag][$abstract]);
		}
	}

	/**
	 * @inheritDoc
	 * @throws ContainerException
	 */
	#[Override]
	public function tagged(string $tag): array
	{
		$this->assertTagContract($tag);

		return array_map(
			fn (string $abstract): object => $this->resolveObject($abstract),
			$this->tags[$tag] ?? []
		);
	}

	/**
	 * @inheritDoc
	 * @throws ContainerException
	 */
	#[Override]
	public function taggedWith(string $tag, string $attribute): array
	{
		$map = [];

		foreach ($this->taggedAbstractsWith($tag, $attribute) as $value => $abstract) {
			$map[$value] = $this->resolveObject($abstract);
		}

		return $map;
	}

	/**
	 * @inheritDoc
	 * @throws ContainerException
	 */
	#[Override]
	public function taggedAbstracts(string $tag): array
	{
		$this->assertTagContract($tag);

		return $this->tags[$tag] ?? [];
	}

	/**
	 * @inheritDoc
	 * @throws ContainerException
	 */
	#[Override]
	public function taggedAbstractsWith(string $tag, string $attribute): array
	{
		$this->assertTagContract($tag);

		$map = [];

		foreach ($this->tags[$tag] ?? [] as $abstract) {
			$value = $this->tagAttributes[$tag][$abstract][$attribute] ?? null;

			if (is_int($value) || is_string($value)) {
				$map[$value] = $abstract;
			}
		}

		return $map;
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function hasTag(string $tag): bool
	{
		return ($this->tags[$tag] ?? []) !== [];
	}

	/**
	 * Assert that a single abstract is a concrete class of the tag's
	 * contract, throwing otherwise. Shared by every enforcement point.
	 *
	 * @throws ContainerException
	 */
	private function assertTagIsConcreteOf(string $tag, string $abstract, string $contract): void
	{
		if (! class_exists($abstract) || ! is_a($abstract, $contract, true)) {
			throw new ContainerException(sprintf(
				'Tag "%s" requires each member to be a concrete class of "%s"; "%s" is not.',
				$tag,
				$contract,
				$abstract
			));
		}
	}

	/**
	 * Assert that every member currently assigned to the tag satisfies its
	 * contract. A no-op for an untyped tag, so it is safe to call from any
	 * resolution path as a backstop against members tagged before the
	 * contract was declared.
	 *
	 * @throws ContainerException
	 */
	private function assertTagContract(string $tag): void
	{
		$contract = $this->tagContracts[$tag] ?? null;

		if ($contract === null) {
			return;
		}

		foreach ($this->tags[$tag] ?? [] as $abstract) {
			$this->assertTagIsConcreteOf($tag, $abstract, $contract);
		}
	}

	/**
	 * Confirms a resolved service is an instance of the class it was
	 * resolved by, so a binding to an incompatible concrete fails loudly at
	 * resolution instead of wherever the object is first used.
	 *
	 * @template T of object
	 * @param    class-string<T> $abstract
	 * @return   T
	 * @throws   ContainerException
	 */
	private function assertInstanceOf(string $abstract, object $service): object
	{
		if (! $service instanceof $abstract) {
			throw new ContainerException(sprintf(
				'Service "%s" resolved to an instance of "%s", which does not satisfy it.',
				$abstract,
				$service::class
			));
		}

		return $service;
	}

	/**
	 * Narrows deferred-call overrides to the named parameters `make()`
	 * accepts, rejecting positional ones.
	 *
	 * @param  array<array-key, mixed> $parameters
	 * @return array<string, mixed>
	 * @throws ContainerException
	 */
	private function namedParameters(array $parameters): array
	{
		$named = [];

		foreach ($parameters as $name => $value) {
			if (! is_string($name)) {
				throw new ContainerException('Parameter overrides must be keyed by parameter name.');
			}

			$named[$name] = $value;
		}

		return $named;
	}

	/**
	 * Resolve an abstract that must produce an object, as `make()`,
	 * `build()`, and tag resolution require.
	 *
	 * @param  array<string, mixed> $parameters
	 * @throws ContainerException
	 */
	private function resolveObject(string $abstract, array $parameters = [], bool $fresh = false): object
	{
		$service = $this->resolve($abstract, $parameters, $fresh);

		if (! is_object($service)) {
			throw new ContainerException(sprintf(
				'Service "%s" resolved to %s, but an object was expected.',
				$abstract,
				get_debug_type($service)
			));
		}

		return $service;
	}

	/**
	 * Resolve a service from the container, optionally with named constructor
	 * overrides. A parameterized resolution is never cached. When `$fresh`
	 * is `true`, any cached singleton is ignored and left untouched: a new
	 * instance is built and not stored. Freshness applies only to this
	 * abstract. It follows delegation to a bound concrete but is not passed
	 * on to the abstract's own dependencies, which resolve normally.
	 *
	 * @param  array<string, mixed> $parameters
	 * @throws ContainerException
	 */
	private function resolve(string $abstract, array $parameters = [], bool $fresh = false): mixed
	{
		// Follow any alias to its target up front so caching, circular
		// dependency tracking, and binding lookups all key off the
		// canonical identifier.
		$abstract = $this->getAlias($abstract);

		// Return the cached instance if it exists and no parameters are
		// provided, unless a fresh instance was requested. Singletons
		// are cached as instances once they are resolved.
		if (! $fresh && $parameters === [] && array_key_exists($abstract, $this->instances)) {
			return $this->instances[$abstract];
		}

		// Detect circular dependencies before recursing any further.
		if (in_array($abstract, $this->buildStack, true)) {
			throw new ContainerException(sprintf(
				'Circular dependency detected while resolving "%s": %s.',
				$abstract,
				implode(' -> ', [...$this->buildStack, $abstract])
			));
		}

		// Track this abstract for the duration of the build so nested
		// resolutions can detect a cycle back to it.
		$this->buildStack[] = $abstract;

		try {
			$concrete = $this->getConcrete($abstract);

			// Delegation: when a binding maps the abstract to a
			// *different* identifier (an interface bound to a concrete
			// class, say), resolve that identifier in its own right
			// rather than building it blindly here. This routes through
			// the concrete's own binding, lifetime, `#[Singleton]`, and
			// hooks, so a shared concrete reached via an interface is
			// the same instance you would get resolving it directly.
			// The concrete's hooks run inside the nested call, and the
			// abstract's own hooks are layered on top below.
			if (
				is_string($concrete)
				&& $concrete !== $abstract
				&& ($this->registered($concrete) || class_exists($concrete))
			) {
				// Delegation targets the same logical entity, so a
				// fresh request carries through; the concrete is what
				// actually gets built anew.
				$service = $this->resolveObject($concrete, $parameters, $fresh);
			} else {
				$service = $this->instantiate($abstract, $concrete, $parameters);
			}

			// Apply any decorators registered for this abstract. Each
			// may wrap or replace the instance, so this runs before
			// caching to ensure the stored copy is the decorated one.
			$service = $this->applyDecorators($abstract, $service);

			// Decide whether to cache the instance. Explicit bindings
			// take precedence; for autowired classes (those with no
			// binding), `#[Singleton]` (or a `#[SingletonWhen]` whose
			// condition currently holds) opts the class into singleton
			// lifetime. Parameterized builds are never cached.
			$shared = $this->isShared($abstract);

			if (! $shared && ! isset($this->bindings[$abstract]) && is_string($concrete)) {
				$shared = $this->declaresSingleton($concrete);
			}

			if ($shared && $parameters === [] && ! $fresh) {
				$this->instances[$abstract] = $service;
			}

			// Let registered callbacks observe the built instance. This
			// runs after caching so a singleton's callbacks see the
			// same shared object that later resolutions return.
			$this->notifyResolving($abstract, $service);

			return $service;
		} finally {
			// Always pop, even on failure, so a thrown-and-caught
			// resolution does not corrupt the stack for later calls.
			array_pop($this->buildStack);
		}
	}

	/**
	 * Run any callbacks registered for the given abstract against the freshly
	 * built instance.
	 */
	private function notifyResolving(string $abstract, object $instance): void
	{
		foreach ($this->resolvingCallbacks[$abstract] ?? [] as $callback) {
			$callback($instance, $this);
		}
	}

	/**
	 * Apply any decorators registered for the given abstract, returning the
	 * final (possibly wrapped) instance.
	 */
	private function applyDecorators(string $abstract, object $instance): object
	{
		foreach ($this->decorators[$abstract] ?? [] as $decorator) {
			$instance = $decorator($instance, $this);
		}

		return $instance;
	}

	/**
	 * Whether the abstract should be treated as shared: either it already has
	 * a cached instance (a resolved singleton or an `instance()` value), or its
	 * binding opts into singleton lifetime.
	 */
	private function isShared(string $abstract): bool
	{
		return array_key_exists($abstract, $this->instances)
			|| ($this->bindings[$abstract]['shared'] ?? false);
	}

	/**
	 * Whether a concrete class opts into singleton lifetime through
	 * `#[Singleton]`, or through a `#[SingletonWhen]` whose condition
	 * currently evaluates truthy. The condition is evaluated fresh on every
	 * call.
	 *
	 * @throws ContainerException
	 */
	private function declaresSingleton(string $concrete): bool
	{
		if (! class_exists($concrete)) {
			return false;
		}

		$plan = $this->plan($concrete);

		if ($plan->singleton) {
			return true;
		}

		return $plan->singletonWhen !== null
			&& $this->conditionIsTrue($this->attributeInstance($plan->singletonWhen)->condition);
	}

	/**
	 * Evaluate a `singletonWhen()`/`transientWhen()`/`#[SingletonWhen]`
	 * condition: a bool is used as-is, a Closure is invoked with the
	 * container, and anything else is treated as a callable and run through
	 * `call()` so it is autowired like any other container callback.
	 *
	 * @param  Condition $condition
	 * @throws ContainerException
	 */
	private function conditionIsTrue(Closure|string|array|bool $condition): bool
	{
		if (is_bool($condition)) {
			return $condition;
		}

		return (bool) ($condition instanceof Closure
			? $condition($this)
			: $this->call($condition));
	}

	/**
	 * Returns the plan for a class, converting a reflection failure into a
	 * container exception.
	 *
	 * @param  class-string $class
	 * @throws ContainerException
	 */
	private function plan(string $class): ClassPlan
	{
		try {
			return $this->planner->forClass($class);
		} catch (ReflectionException $e) {
			throw new ContainerException(
				sprintf('Failed to plan "%s": %s', $class, $e->getMessage()),
				previous: $e
			);
		}
	}

	/**
	 * Returns the attribute instance for a plan spec, building it once.
	 *
	 * @template T of object
	 * @param    AttributeSpec<T> $spec
	 * @return   T
	 */
	private function attributeInstance(AttributeSpec $spec): object
	{
		/** @var T */
		return $this->attributeInstances[$spec] ??= $spec->newInstance();
	}

	/**
	 * Determine if the given concrete is buildable.
	 */
	private function isBuildable(mixed $concrete): bool
	{
		return $concrete instanceof Closure
			|| (is_string($concrete) && class_exists($concrete));
	}

	/**
	 * Follow an alias chain to the canonical identifier it points at,
	 * returning the abstract unchanged when it is not aliased. A cycle in the
	 * chain is reported rather than looping indefinitely.
	 *
	 * @throws ContainerException
	 */
	private function getAlias(string $abstract): string
	{
		$seen = [];

		while (isset($this->aliases[$abstract])) {
			if (isset($seen[$abstract])) {
				throw new ContainerException(sprintf(
					'Circular alias detected while resolving "%s".',
					$abstract
				));
			}

			$seen[$abstract] = true;
			$abstract = $this->aliases[$abstract];
		}

		return $abstract;
	}

	/**
	 * Get the concrete bound to an abstract, or the abstract itself (for
	 * auto-resolution) when it has no binding.
	 */
	private function getConcrete(string $abstract): mixed
	{
		return isset($this->bindings[$abstract])
			? $this->bindings[$abstract]['concrete']
			: $abstract;
	}

	/**
	 * Instantiate the given concrete. A closure concrete is treated as a
	 * factory and invoked as `fn(ServiceResolver $resolver, array
	 * $parameters): object`; a class-name concrete is built from its plan
	 * with its autowired dependencies.
	 *
	 * @param  array<string, mixed> $parameters Named constructor overrides.
	 * @throws ContainerException
	 */
	private function instantiate(string $abstract, mixed $concrete, array $parameters = []): object
	{
		// A closure is a factory. Exceptions thrown by a user-supplied
		// factory propagate as-is.
		if ($concrete instanceof Closure) {
			$service = $concrete($this, $parameters);

			if (! is_object($service)) {
				throw new ContainerException(sprintf(
					'The factory for "%s" returned %s, but an object was expected.',
					$abstract,
					get_debug_type($service)
				));
			}

			return $service;
		}

		// Distinguish between an unknown identifier and a
		// registered-but-unbuildable binding so consumers can handle each
		// case differently.
		if (! is_string($concrete) || ! class_exists($concrete)) {
			if ($this->registered($abstract)) {
				throw new ContainerException(sprintf(
					'Service "%s" is registered but its bound concrete cannot be built.',
					$abstract
				));
			}

			throw new NotFoundException(sprintf(
				'Service "%s" is not registered and could not be resolved.',
				$abstract
			));
		}

		$plan = $this->plan($concrete);

		if (! $plan->instantiable) {
			throw new ContainerException(sprintf(
				'Failed to build "%s": the class is not instantiable.',
				$concrete
			));
		}

		// Low-level instantiation failures (an `Error` thrown by the
		// constructor, for example) are wrapped so the container only
		// ever throws a ContainerException, while nested
		// ContainerExceptions from dependency resolution propagate
		// unchanged.
		try {
			if ($plan->parameters === null) {
				return new $concrete();
			}

			// The concrete being built is the "consumer" for any
			// contextual bindings its parameters may match.
			return new $concrete(...$this->resolveDependencies(
				$plan->parameters,
				$parameters,
				$concrete,
				$concrete
			));
		} catch (Error $e) {
			throw new ContainerException(
				sprintf('Failed to build "%s": %s', $concrete, $e->getMessage()),
				previous: $e
			);
		}
	}

	/**
	 * Reflect a callable into its parameter source and an invokable form.
	 * A `[class-string, 'method']` pair (or a non-static method written as
	 * `'Class::method'`) has its object resolved from the container first so
	 * the instance is autowired before the method is invoked. Methods are
	 * bound via closures so non-public members can be called.
	 *
	 * @param  callable|array{0: object|string, 1: string} $callback
	 * @return array{0: ReflectionFunctionAbstract, 1: callable}
	 * @throws ContainerException|ReflectionException
	 */
	private function reflectCallback(callable|array $callback): array
	{
		// `[$objectOrClass, 'method']`
		if (is_array($callback)) {
			[$target, $method] = $callback;
			$reflector = new ReflectionMethod($target, $method);

			// Static methods need no instance.
			if ($reflector->isStatic()) {
				return [$reflector, $reflector->getClosure()];
			}

			$object = is_object($target) ? $target : $this->resolveObject($target);

			return [$reflector, $reflector->getClosure($object)];
		}

		// `'Class::method'`
		if (is_string($callback) && str_contains($callback, '::')) {
			$reflector = ReflectionMethod::createFromMethodName($callback);

			if ($reflector->isStatic()) {
				return [$reflector, $callback];
			}

			// A non-static method named in static form still needs an
			// instance, so resolve its declaring class.
			$object = $this->resolveObject($reflector->getDeclaringClass()->getName());

			return [$reflector, $reflector->getClosure($object)];
		}

		// Invokable object (anything but a closure).
		if (is_object($callback) && ! $callback instanceof Closure) {
			return [new ReflectionMethod($callback, '__invoke'), $callback];
		}

		// Closure or function name.
		$closure = $callback(...);

		return [new ReflectionFunction($closure), $closure];
	}

	/**
	 * Describes a function or method for error messages.
	 */
	private function describeFunction(ReflectionFunctionAbstract $function): string
	{
		return $function instanceof ReflectionMethod
			? "{$function->class}::{$function->getName()}()"
			: "{$function->getName()}()";
	}

	/**
	 * Resolve a list of parameters. `$consumer` is the concrete class being
	 * built, used to match contextual bindings; it is `null` for a free
	 * callable resolved through `call()`, which has no consumer. `$owner`
	 * names the class or function for error messages.
	 *
	 * @param  list<ParameterPlan>  $params
	 * @param  array<string, mixed> $providedParams
	 * @return list<mixed>
	 * @throws ContainerException
	 */
	private function resolveDependencies(array $params, array $providedParams, ?string $consumer, string $owner): array
	{
		$dependencies = [];

		foreach ($params as $param) {
			$value = $this->resolveParameter($param, $providedParams, $consumer, $owner);

			// A variadic slot is filled by spreading a resolved
			// collection across it, so each element arrives as its own
			// positional argument and PHP enforces the parameter's type
			// on every item. A non-array value is passed as-is.
			if ($param->variadic && is_array($value)) {
				array_push($dependencies, ...array_values($value));
			} else {
				$dependencies[] = $value;
			}
		}

		return $dependencies;
	}

	/**
	 * Resolve a single parameter, in order of precedence: an explicitly
	 * provided argument, a parameter attribute (`#[NoAutowire]` or a
	 * contextual attribute), a contextual binding registered for the
	 * consumer, autowiring from the parameter's type, and finally a
	 * fallback to a default value, `null`, or failure.
	 *
	 * @param  array<string, mixed> $providedParams
	 * @throws ContainerException
	 */
	private function resolveParameter(ParameterPlan $param, array $providedParams, ?string $consumer, string $owner): mixed
	{
		// An explicitly provided argument always wins.
		if (array_key_exists($param->name, $providedParams)) {
			return $providedParams[$param->name];
		}

		// A `#[NoAutowire]` marker suppresses type-based autowiring,
		// deferring to whatever fallback the signature allows.
		if ($param->noAutowire) {
			return $this->resolveFallback($param, $owner);
		}

		// A contextual attribute resolves its own value and takes
		// precedence over type-based autowiring.
		if ($param->contextual !== null) {
			try {
				return $this->attributeInstance($param->contextual)->resolve($this);
			} catch (NotFoundException $e) {
				if ($param->hasFallback()) {
					return $this->resolveFallback($param, $owner);
				}

				throw $e;
			}
		}

		// A contextual binding lets a specific consumer override how a
		// parameter is supplied: by name (a value the container cannot
		// autowire, such as a scalar) or by type (a per-consumer
		// implementation swap).
		if ($consumer !== null && isset($this->contextual[$consumer])) {
			$bindings = $this->contextual[$consumer];

			// By name first: the most specific, and the path for a
			// scalar the container has no other way to supply. The
			// bound value is passed as-is (or computed, for a closure),
			// never resolved as a class.
			if (array_key_exists($param->name, $bindings['params'] ?? [])) {
				$give = $bindings['params'][$param->name];

				return $give instanceof Closure ? $give($this) : $give;
			}

			// Then by type, limited to a single named, non-builtin
			// type. A closure is computed; a class-string is resolved
			// through the container so its own binding, lifetime, and
			// hooks apply.
			if ($param->typeName !== null && array_key_exists($param->typeName, $bindings['types'] ?? [])) {
				$give = $bindings['types'][$param->typeName];

				return $give instanceof Closure ? $give($this) : $this->resolve($give);
			}
		}

		// A variadic parameter is inherently optional. With nothing
		// provided to fill it, the container contributes an empty set,
		// which resolveDependencies() spreads into zero arguments.
		if ($param->variadic) {
			return [];
		}

		// Autowire from the type, falling back to a default value, `null`
		// when the parameter is nullable, or failing.
		return $this->autowireParameter($param) ?? $this->resolveFallback($param, $owner);
	}

	/**
	 * Autowire a dependency from a parameter type, returning the resolved
	 * value or `null` when the type cannot be satisfied.
	 *
	 * A registered binding is preferred over a class that merely exists, so
	 * the bindings pass runs across every alternative before the build
	 * pass; the first alternative satisfied wins.
	 *
	 * A class can exist yet not be autowirable (a value object whose
	 * constructor takes a scalar, say). Building one throws, which means
	 * only that this alternative is unsatisfied, so a union still gets to
	 * try its remaining members. If nothing is satisfiable and the
	 * parameter has its own fallback (a default or a nullable type), the
	 * failure is swallowed; otherwise the first build failure is re-thrown.
	 *
	 * @throws ContainerException
	 */
	private function autowireParameter(ParameterPlan $param): mixed
	{
		$alternatives = $param->alternatives;

		$bound = $this->firstSatisfied(
			$alternatives,
			fn (string $className): mixed => $this->registered($className) ? $this->resolve($className) : null
		);

		if ($bound !== null) {
			return $bound;
		}

		$failure = null;

		$built = $this->firstSatisfied(
			$alternatives,
			function (string $className) use (&$failure): mixed {
				if (! class_exists($className)) {
					return null;
				}

				try {
					return $this->resolveObject($className);
				} catch (ContainerException $e) {
					$failure ??= $e;

					return null;
				}
			}
		);

		if ($built !== null) {
			return $built;
		}

		if ($failure !== null && ! $param->hasFallback()) {
			throw $failure;
		}

		return null;
	}

	/**
	 * Returns the first alternative that `$acquire` can satisfy, or `null`.
	 * Each alternative is a set of class names a single object must satisfy
	 * together; `$acquire` turns a class name into a candidate, or `null`
	 * when it has nothing to offer for that name.
	 *
	 * @param  list<list<class-string>>      $alternatives
	 * @param  callable(class-string): mixed $acquire
	 * @throws ContainerException
	 */
	private function firstSatisfied(array $alternatives, callable $acquire): mixed
	{
		foreach ($alternatives as $members) {
			foreach ($members as $className) {
				$candidate = $acquire($className);

				if ($this->candidateSatisfies($candidate, $members)) {
					return $candidate;
				}
			}
		}

		return null;
	}

	/**
	 * Whether a candidate satisfies an alternative. A single-member
	 * alternative is trusted as-is (any non-null value); a multi-member
	 * (intersection) alternative requires an object that is an instance of
	 * every member.
	 *
	 * @param list<class-string> $members
	 */
	private function candidateSatisfies(mixed $candidate, array $members): bool
	{
		if ($candidate === null) {
			return false;
		}

		return count($members) === 1
			|| (is_object($candidate) && array_all(
				$members,
				static fn (string $member): bool => $candidate instanceof $member
			));
	}

	/**
	 * Resolve a parameter that cannot be autowired by falling back to its
	 * default value or `null` when the signature permits it.
	 *
	 * @throws ContainerException
	 */
	private function resolveFallback(ParameterPlan $param, string $owner): mixed
	{
		if ($param->hasDefault) {
			return $param->defaultValue();
		}

		if ($param->nullable) {
			return null;
		}

		throw new ContainerException(sprintf(
			'Unresolvable dependency: parameter "$%s"%s in %s could not be resolved.',
			$param->name,
			$param->typeString === null ? '' : " of type {$param->typeString}",
			$owner
		));
	}
}
