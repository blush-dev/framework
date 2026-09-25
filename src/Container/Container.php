<?php

/**
 * Container interface.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Container;

use Closure;
use UnitEnum;
use Psr\Container\ContainerInterface;

/**
 * Defines the dependency injection container interface. It binds concrete
 * implementations to abstracts — as transients, singletons, or existing
 * instances — and autowires dependencies when resolving. It also supports
 * tagging, deferred resolution, and lifecycle hooks (`resolving()`,
 * `decorate()`) for observing and decorating resolved services. It is also a
 * PSR-11 container.
 *
 * @phpstan-type Condition Closure|callable-string|array{0: object|string, 1: string}|bool
 */
interface Container extends ServiceResolver, ContainerInterface
{
	/**
	 * Register a singleton service (cached instance).
	 */
	public function singleton(string $abstract, mixed $concrete = null): void;

	/**
	 * Register a singleton service only if the abstract is not already
	 * bound, so an existing binding (such as one registered by an extension)
	 * is left in place.
	 */
	public function singletonIf(string $abstract, mixed $concrete = null): void;

	/**
	 * Register a singleton service only if the given condition evaluates
	 * truthy. A bool is used as-is; a Closure is invoked with the container;
	 * any other callable is run through `call()` so it is autowired like any
	 * other container callback. Nothing is bound when the condition is falsy.
	 *
	 * @param Condition $condition
	 */
	public function singletonWhen(
		string $abstract,
		Closure|string|array|bool $condition,
		mixed $concrete = null
	): void;

	/**
	 * Register a transient service (new instance each time).
	 */
	public function transient(string $abstract, mixed $concrete = null): void;

	/**
	 * Register a transient service only if the abstract is not already
	 * bound, so an existing binding (such as one registered by an extension)
	 * is left in place.
	 */
	public function transientIf(string $abstract, mixed $concrete = null): void;

	/**
	 * Register a transient service only if the given condition evaluates
	 * truthy. See `singletonWhen()` for how the condition is evaluated.
	 * Nothing is bound when the condition is falsy.
	 *
	 * @param Condition $condition
	 */
	public function transientWhen(
		string $abstract,
		Closure|string|array|bool $condition,
		mixed $concrete = null
	): void;

	/**
	 * Register an existing value as a singleton. The value is stored and
	 * returned as-is; it is never built or autowired by the container.
	 */
	public function instance(string $abstract, mixed $instance): void;

	/**
	 * Register an alias so that resolving `$alias` resolves `$abstract`
	 * instead, returning the same instance and lifetime. Aliases are
	 * followed transitively, so an alias may point at another alias. An
	 * identifier is either an alias or a binding, never both: aliasing a
	 * name drops any binding registered under it, and binding a name drops
	 * any alias under it.
	 */
	public function alias(string $alias, string $abstract): void;

	/**
	 * Register a contextual binding by parameter name: when the container
	 * builds `$consumer`, the constructor parameter named `$param` is given
	 * `$value` instead of being autowired. This is the way to supply a value
	 * the container cannot resolve by type — a scalar, an array, and so on.
	 * The value is passed as-is, or, when it is a `Closure(Container): mixed`,
	 * the closure's return value is used. The parameter name is given without
	 * a leading `$`.
	 */
	public function whenNeedsParam(string $consumer, string $param, mixed $value): void;

	/**
	 * Register a contextual binding by type: when the container builds
	 * `$consumer`, a constructor parameter typed `$type` is satisfied by
	 * `$concrete` instead of the type's usual binding, so one consumer can
	 * be given a different implementation than the rest of the application.
	 * The concrete is a class-string resolved through the container (honoring
	 * its own binding, lifetime, and hooks), or a `Closure(Container): mixed`
	 * whose return value is used. Only a single, non-builtin type is matched.
	 */
	public function whenNeedsType(string $consumer, string $type, Closure|string $concrete): void;

	/**
	 * Sets a container-backed named parameter value. A parameter set this
	 * way is available to any constructor parameter explicitly marked with
	 * the `#[Param]` attribute and sharing the same name.
	 *
	 * Calling this again for an already-set `$parameter` overwrites the
	 * previous value.
	 *
	 * @param array<array-key, mixed>|bool|string|int|float|UnitEnum|null $value
	 */
	public function setParam(string $parameter, array|bool|string|int|float|UnitEnum|null $value): void;

	/**
	 * Gets a previously set container parameter value.
	 *
	 * @return array<array-key, mixed>|bool|string|int|float|UnitEnum|null
	 * @throws NotFoundException When no value has been set for `$parameter`.
	 */
	public function getParam(string $parameter): array|bool|string|int|float|UnitEnum|null;

	/**
	 * Whether a value has been set for the given parameter name.
	 */
	public function hasParam(string $parameter): bool;

	/**
	 * Resolve a service from the container by its identifier, returning the
	 * bound value or an autowired instance. Use `make()` to pass constructor
	 * overrides or for a guaranteed object return.
	 */
	public function get(string $abstract): mixed;

	/**
	 * Invoke a callable, resolving its parameters from the container.
	 * Values in `$parameters` are matched by name and take precedence over
	 * type-based resolution. The array form of `$callback` accepts a
	 * `[class-string, 'method']` pair, whose object is resolved from the
	 * container before the method is invoked.
	 *
	 * @param callable|array{0: object|string, 1: string} $callback
	 * @param array<string, mixed>                        $parameters
	 */
	public function call(callable|array $callback, array $parameters = []): mixed;

	/**
	 * Return a closure that resolves the given abstract on each call, so a
	 * consumer can create instances on demand without depending on the
	 * container itself. Values passed to the returned closure are matched by
	 * name and take precedence over type-based resolution, mirroring `make()`.
	 *
	 * @return Closure(array<string, mixed>=): object
	 */
	public function defer(string $abstract): Closure;

	/**
	 * Register a callback run after the given abstract is built, before it is
	 * returned. The callback receives the resolved instance and a resolver,
	 * and is expected to mutate the instance in place. Callbacks run once per
	 * build, so a resolved singleton is only observed the first time it is
	 * created.
	 *
	 * @param Closure(object, ServiceResolver): void $callback
	 */
	public function resolving(string $abstract, Closure $callback): void;

	/**
	 * Register a callback that decorates the given abstract after it is built.
	 * The callback receives the resolved instance and a resolver, and must
	 * return the instance to use in its place — typically a wrapper that adds
	 * behavior while satisfying the same contract. Though any replacement
	 * honoring the contract is allowed (a reconfigured copy or an alternative
	 * implementation). Multiple decorators stack in registration order,
	 * each wrapping the result of the previous one. If the abstract is already
	 * resolved or registered, the decorator is applied to the stored instance
	 * immediately.
	 *
	 * @param Closure(object, ServiceResolver): object $closure
	 */
	public function decorate(string $abstract, Closure $closure): void;

	/**
	 * Check whether the container can resolve the given abstract — that is,
	 * whether `get()` would return without throwing a not-found error. This
	 * is true for a registered abstract or any buildable (existing) class,
	 * so an auto-wirable class reports `true` even without an explicit binding.
	 */
	public function has(string $abstract): bool;

	/**
	 * Check whether the abstract has been explicitly registered as a binding
	 * or an instance. Unlike `has()`, this is `false` for a class that is
	 * merely auto-wirable.
	 */
	public function registered(string $abstract): bool;

	/**
	 * Check whether an instance for the abstract is already cached — either
	 * a resolved singleton or a value registered via `instance()`.
	 */
	public function resolved(string $abstract): bool;

	/**
	 * Forget a resolved singleton or registered instance so the next
	 * resolution rebuilds it. The binding and any `resolving()`/`decorate()`
	 * hooks are left in place and re-applied on the next build.
	 */
	public function forgetInstance(string $abstract): void;

	/**
	 * Assign one or more abstracts to a tag so they can be resolved together.
	 * Abstracts already assigned to the tag are ignored, so a tag never holds
	 * duplicates.
	 *
	 * If the tag has a contract (see `setTagContract()`), each member is
	 * validated as it is tagged and a mistagged one is rejected up front.
	 *
	 * @param string|array<string>  $abstracts
	 * @param array<string, mixed>  $attributes
	 * @throws ContainerException When a member violates the tag's contract.
	 */
	public function tag(string|array $abstracts, string $tag, array $attributes = []): void;

	/**
	 * Reads every `#[Tag]` attribute declared on `$class` and assigns it to
	 * each named tag with the attribute's recorded values, exactly as if
	 * `tag()` had been called directly. A no-op for a class with no `#[Tag]`
	 * attributes.
	 *
	 * @throws ContainerException When a member violates a tag's contract.
	 */
	public function tagFromAttributes(string $class): void;

	/**
	 * Type a tag: declare that every member must be a concrete class of the
	 * given contract. This makes the tag a homogeneous set of implementations
	 * — `tagged()` yields `$contract` instances and `taggedAbstracts()`
	 * yields their concrete classes.
	 *
	 * The contract is enforced at every point it can be: members already
	 * tagged are validated now, members tagged later are validated by
	 * `tag()`, and every resolution (`tagged()`, `taggedAbstracts()`, and
	 * their `*With` variants) validates as a backstop — so a violation is
	 * caught regardless of when the offending member was tagged.
	 *
	 * The contract is set once; a later call naming a different contract
	 * for the same tag is an error.
	 *
	 * @param class-string $contract
	 * @throws ContainerException When a member already tagged violates the
	 *                            contract, or a conflicting contract is
	 *                            declared.
	 */
	public function setTagContract(string $tag, string $contract): void;

	/**
	 * Remove one or more abstracts from a tag, leaving the rest in place.
	 *
	 * @param string|array<string> $abstracts
	 */
	public function untag(string|array $abstracts, string $tag): void;

	/**
	 * Resolve every abstract assigned to the given tag.
	 *
	 * @return array<object>
	 */
	public function tagged(string $tag): array;

	/**
	 * Resolve every abstract assigned to a given tag and with a specific
	 * attribute defined, keyed by that attribute's value.
	 *
	 * @return array<array-key, object>
	 */
	public function taggedWith(string $tag, string $attribute): array;

	/**
	 * Return the abstracts assigned to the given tag without resolving them,
	 * for inspection or lazy resolution. The order matches assignment order,
	 * and an unknown tag yields an empty array. For a typed tag (see
	 * `setTagContract()`), every member is a concrete class of that contract,
	 * so the returned identifiers are ready to inspect (static methods,
	 * `is_subclass_of()`) and pass to `make()`.
	 *
	 * @return array<string>
	 */
	public function taggedAbstracts(string $tag): array;

	/**
	 * Returns a map from a chosen attribute's value to its abstract, for
	 * every member of `$tag` that was given that attribute. Building this
	 * map never constructs a service — it only reads the attributes
	 * recorded at tag() time — so it's suited to a large tag group where
	 * just one member will actually be resolved:
	 *
	 *   $bySlug   = $container->taggedAbstractsWith(Markup::TAG, 'slug');
	 *   $instance = $container->make($bySlug[$slug]);
	 *
	 * @return array<array-key, string>
	 */
	public function taggedAbstractsWith(string $tag, string $attribute): array;

	/**
	 * Check whether any abstracts are currently assigned to the given tag. A
	 * tag whose abstracts have all been removed reports `false`, the same as
	 * a tag that was never assigned.
	 */
	public function hasTag(string $tag): bool;
}
