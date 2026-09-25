<?php

/**
 * Service container tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Container\Attributes\Tagged;
use Blush\Container\Container;
use Blush\Container\ContainerException;
use Blush\Container\NotFoundException;
use Blush\Container\ServiceContainer;
use Blush\Tests\Fixtures\Container\AlsoNeedsCache;
use Blush\Tests\Fixtures\Container\BareVariadicCollector;
use Blush\Tests\Fixtures\Container\Cache;
use Blush\Tests\Fixtures\Container\CacheAvailability;
use Blush\Tests\Fixtures\Container\CacheCollector;
use Blush\Tests\Fixtures\Container\ConfigurableCache;
use Blush\Tests\Fixtures\Container\FileCache;
use Blush\Tests\Fixtures\Container\LoggingCache;
use Blush\Tests\Fixtures\Container\MakeFreshConsumer;
use Blush\Tests\Fixtures\Container\MakeFreshUnknownConsumer;
use Blush\Tests\Fixtures\Container\NeedsApiKey;
use Blush\Tests\Fixtures\Container\NeedsCache;
use Blush\Tests\Fixtures\Container\NeedsStatus;
use Blush\Tests\Fixtures\Container\NoAutowireCache;
use Blush\Tests\Fixtures\Container\NullCache;
use Blush\Tests\Fixtures\Container\OptionalValueObject;
use Blush\Tests\Fixtures\Container\ReportBuilder;
use Blush\Tests\Fixtures\Container\RequiresValueObject;
use Blush\Tests\Fixtures\Container\SingletonWhenFalseCache;
use Blush\Tests\Fixtures\Container\SingletonWhenTrueCache;
use Blush\Tests\Fixtures\Container\UnionValueObject;
use Blush\Tests\Fixtures\Container\ValueObject;

#[CoversClass(ServiceContainer::class)]
final class ServiceContainerTest extends TestCase
{
	private ServiceContainer $container;

	protected function setUp(): void
	{
		$this->container = new ServiceContainer();
	}

	public function testSingletonReturnsTheSameInstance(): void
	{
		$this->container->singleton(Cache::class, FileCache::class);

		$this->assertSame(
			$this->container->get(Cache::class),
			$this->container->get(Cache::class)
		);
	}

	public function testTransientReturnsAFreshInstance(): void
	{
		$this->container->transient(Cache::class, FileCache::class);

		$this->assertNotSame(
			$this->container->get(Cache::class),
			$this->container->get(Cache::class)
		);
	}

	public function testSingletonWhenBindsAsSingletonWhenConditionIsTrue(): void
	{
		$this->container->singletonWhen(Cache::class, true, FileCache::class);

		$this->assertSame(
			$this->container->get(Cache::class),
			$this->container->get(Cache::class)
		);
	}

	public function testSingletonWhenBindsNothingWhenConditionIsFalse(): void
	{
		$this->container->singletonWhen(Cache::class, false, FileCache::class);

		$this->expectException(NotFoundException::class);

		$this->container->get(Cache::class);
	}

	public function testTransientWhenBindsAsTransientWhenConditionIsTrue(): void
	{
		$this->container->transientWhen(Cache::class, true, FileCache::class);

		$this->assertNotSame(
			$this->container->get(Cache::class),
			$this->container->get(Cache::class)
		);
	}

	public function testTransientWhenBindsNothingWhenConditionIsFalse(): void
	{
		$this->container->transientWhen(Cache::class, false, FileCache::class);

		$this->expectException(NotFoundException::class);

		$this->container->get(Cache::class);
	}

	public function testSingletonWhenEvaluatesAClosureConditionWithTheContainer(): void
	{
		$this->container->singleton(Cache::class, FileCache::class);

		$this->container->singletonWhen(
			NullCache::class,
			fn (ServiceContainer $container): bool => $container->get(Cache::class) instanceof Cache
		);

		$this->assertSame(
			$this->container->get(NullCache::class),
			$this->container->get(NullCache::class)
		);
	}

	public function testSingletonWhenEvaluatesAnArrayCallableConditionWithAutowiring(): void
	{
		$this->container->singleton(Cache::class, FileCache::class);

		$this->container->singletonWhen(
			NullCache::class,
			[CacheAvailability::class, 'cacheIsAvailable']
		);

		$this->assertSame(
			$this->container->get(NullCache::class),
			$this->container->get(NullCache::class)
		);
	}

	public function testSingletonWhenAttributeSharesInstanceWhenConditionIsTrue(): void
	{
		$this->assertSame(
			$this->container->get(SingletonWhenTrueCache::class),
			$this->container->get(SingletonWhenTrueCache::class)
		);
	}

	public function testSingletonWhenAttributeDoesNotShareInstanceWhenConditionIsFalse(): void
	{
		$this->assertNotSame(
			$this->container->get(SingletonWhenFalseCache::class),
			$this->container->get(SingletonWhenFalseCache::class)
		);
	}

	public function testInstanceIsReturnedAsIs(): void
	{
		$cache = new FileCache();
		$this->container->instance(Cache::class, $cache);

		$this->assertSame($cache, $this->container->get(Cache::class));
	}

	public function testRebindingClearsTheCachedInstance(): void
	{
		$this->container->singleton(Cache::class, FileCache::class);
		$first = $this->container->get(Cache::class);

		$this->container->singleton(Cache::class, FileCache::class);

		$this->assertNotSame($first, $this->container->get(Cache::class));
	}

	public function testAutowiresConstructorDependencies(): void
	{
		$this->container->singleton(Cache::class, FileCache::class);

		$report = $this->container->make(ReportBuilder::class);

		$this->assertInstanceOf(FileCache::class, $report->cache);
	}

	public function testTaggedServicesResolveTogether(): void
	{
		$this->container->singleton(FileCache::class);
		$this->container->tag(FileCache::class, 'caches');

		$tagged = $this->container->tagged('caches');

		$this->assertCount(1, $tagged);
		$this->assertInstanceOf(FileCache::class, $tagged[0]);
	}

	public function testTaggedServicesSpreadIntoAVariadicParameter(): void
	{
		$this->container->singleton(FileCache::class);
		$this->container->singleton(NullCache::class);
		$this->container->tag([FileCache::class, NullCache::class], 'caches');

		$collector = $this->container->make(CacheCollector::class);

		$this->assertCount(2, $collector->caches);
		$this->assertInstanceOf(FileCache::class, $collector->caches[0]);
		$this->assertInstanceOf(NullCache::class, $collector->caches[1]);
	}

	public function testBareVariadicResolvesToEmpty(): void
	{
		// A variadic with no `#[Tagged]` attribute is inherently optional:
		// with nothing to fill it, the container passes zero arguments
		// rather than autowiring a lone instance or failing on the
		// un-buildable `Cache` interface.
		$collector = $this->container->make(BareVariadicCollector::class);

		$this->assertSame([], $collector->caches);
	}

	public function testCallSpreadsTaggedServicesIntoAVariadicParameter(): void
	{
		$this->container->singleton(FileCache::class);
		$this->container->singleton(NullCache::class);
		$this->container->tag([FileCache::class, NullCache::class], 'caches');

		$collected = $this->container->call(
			fn (FileCache $first, #[Tagged('caches')] Cache ...$caches): array => $caches
		);

		$this->assertIsArray($collected);
		$this->assertCount(2, $collected);
		$this->assertInstanceOf(FileCache::class, $collected[0]);
		$this->assertInstanceOf(NullCache::class, $collected[1]);
	}

	public function testDecorateWrapsTheResolvedInstance(): void
	{
		$this->container->singleton(Cache::class, FileCache::class);
		$this->container->decorate(
			Cache::class,
			function (object $cache): object {
				$this->assertInstanceOf(Cache::class, $cache);

				return new LoggingCache($cache);
			}
		);

		$this->assertInstanceOf(LoggingCache::class, $this->container->get(Cache::class));
	}

	public function testUnknownServiceThrowsNotFound(): void
	{
		$this->expectException(NotFoundException::class);

		$this->container->get('does-not-exist');
	}

	public function testEnumCannotBeAutowired(): void
	{
		$this->expectException(ContainerException::class);

		$this->container->make(NeedsStatus::class);
	}

	public function testOptionalUnbuildableDependencyFallsBackToDefault(): void
	{
		// `ValueObject` exists but cannot be autowired. Because the
		// parameter is optional, the build failure must not escape: the
		// default value is used instead.
		$object = $this->container->make(OptionalValueObject::class);

		$this->assertNull($object->value);
	}

	public function testOptionalUnbuildableDependencyStillUsesABinding(): void
	{
		// A registered binding is preferred over building, so an optional
		// parameter is satisfied when one exists rather than falling back.
		$value = new ValueObject('source');
		$this->container->instance(ValueObject::class, $value);

		$object = $this->container->make(OptionalValueObject::class);

		$this->assertSame($value, $object->value);
	}

	public function testRequiredUnbuildableDependencyStillThrows(): void
	{
		// With no fallback of its own, a required un-autowirable dependency
		// must surface an error rather than being silently skipped.
		$this->expectException(ContainerException::class);

		$this->container->make(RequiresValueObject::class);
	}

	public function testUnionSkipsUnbuildableMemberForABuildableOne(): void
	{
		// Building the `ValueObject` member throws; the union must fall
		// through to the autowirable `FileCache` member instead of failing.
		$object = $this->container->make(UnionValueObject::class);

		$this->assertInstanceOf(FileCache::class, $object->dep);
	}

	public function testNoAutowireKeepsTheDefaultInsteadOfBuilding(): void
	{
		// The `Cache` dependency is autowirable, but `#[NoAutowire]`
		// suppresses that so the parameter keeps its `null` default.
		$object = $this->container->make(NoAutowireCache::class);

		$this->assertNull($object->cache);
	}

	public function testNoAutowireStillYieldsToAnExplicitArgument(): void
	{
		// Suppressing autowiring does not block an explicitly provided
		// argument, which always takes precedence.
		$cache = new FileCache();

		$object = $this->container->make(NoAutowireCache::class, [
			'cache' => $cache
		]);

		$this->assertSame($cache, $object->cache);
	}

	public function testContextualParamBindingSuppliesAScalar(): void
	{
		// Built-in-typed parameters can't be autowired; a name binding
		// supplies them for this consumer.
		$this->container->whenNeedsParam(NeedsApiKey::class, 'apiKey', 'secret');
		$this->container->whenNeedsParam(NeedsApiKey::class, 'timeout', 30);

		$client = $this->container->make(NeedsApiKey::class);

		$this->assertSame('secret', $client->apiKey);
		$this->assertSame(30, $client->timeout);
	}

	public function testContextualParamBindingAcceptsAClosure(): void
	{
		$this->container->instance('config.timeout', 45);
		$this->container->whenNeedsParam(NeedsApiKey::class, 'apiKey', 'key');

		// A closure value is computed against the container.
		$this->container->whenNeedsParam(
			NeedsApiKey::class,
			'timeout',
			fn (Container $container): mixed => $container->get('config.timeout')
		);

		$client = $this->container->make(NeedsApiKey::class);

		$this->assertSame(45, $client->timeout);
	}

	public function testContextualTypeBindingSwapsImplementation(): void
	{
		// The interface resolves to NullCache application-wide...
		$this->container->singleton(Cache::class, NullCache::class);

		// ...but this one consumer is given FileCache instead.
		$this->container->whenNeedsType(NeedsCache::class, Cache::class, FileCache::class);

		$consumer = $this->container->make(NeedsCache::class);

		$this->assertInstanceOf(FileCache::class, $consumer->cache);
	}

	public function testContextualBindingIsScopedToItsConsumer(): void
	{
		$this->container->singleton(Cache::class, NullCache::class);
		$this->container->whenNeedsType(NeedsCache::class, Cache::class, FileCache::class);

		// A different consumer of Cache still gets the default binding.
		$other = $this->container->make(AlsoNeedsCache::class);

		$this->assertInstanceOf(NullCache::class, $other->cache);
	}

	public function testProvidedArgumentOverridesContextualBinding(): void
	{
		// An explicit make() argument outranks a contextual binding.
		$this->container->whenNeedsParam(NeedsApiKey::class, 'apiKey', 'secret');

		$client = $this->container->make(NeedsApiKey::class, [
			'apiKey'  => 'override',
			'timeout' => 10
		]);

		$this->assertSame('override', $client->apiKey);
	}

	public function testMakeFreshBuildsANewInstanceIgnoringTheSingletonCache(): void
	{
		$this->container->singleton(FileCache::class);
		$shared = $this->container->get(FileCache::class);

		$fresh = $this->container->build(FileCache::class);

		// A new instance is returned, and the cached singleton is left
		// untouched — resolving normally still yields the original.
		$this->assertNotSame($shared, $fresh);
		$this->assertSame($shared, $this->container->get(FileCache::class));
	}

	public function testMakeFreshKeepsDependenciesShared(): void
	{
		// Freshness is shallow: NeedsCache is built anew, but the Cache
		// singleton it depends on is still the shared instance.
		$this->container->singleton(Cache::class, NullCache::class);
		$shared = $this->container->make(NeedsCache::class);

		$fresh = $this->container->build(NeedsCache::class);

		$this->assertNotSame($shared, $fresh);
		$this->assertSame($shared->cache, $fresh->cache);
	}

	public function testBuildFollowsDelegationToTheConcrete(): void
	{
		// Resolving the interface delegates to FileCache; build() builds
		// a fresh concrete while leaving the shared concrete in place.
		$this->container->singleton(Cache::class, FileCache::class);
		$shared = $this->container->get(Cache::class);

		$fresh = $this->container->build(Cache::class);

		$this->assertInstanceOf(FileCache::class, $fresh);
		$this->assertNotSame($shared, $fresh);
		$this->assertSame($shared, $this->container->get(Cache::class));
	}

	public function testMakeFreshAttributeBuildsWithInlineOverrides(): void
	{
		$consumer = $this->container->make(MakeFreshConsumer::class);

		$this->assertInstanceOf(ConfigurableCache::class, $consumer->cache);
		$this->assertSame(3600, $consumer->cache->ttl);
	}

	public function testMakeFreshAttributeBypassesTheSharedInstance(): void
	{
		// A shared instance exists with the default TTL...
		$this->container->singleton(ConfigurableCache::class);
		$shared = $this->container->make(ConfigurableCache::class);

		// ...but #[MakeFresh] builds a fresh, unshared one.
		$consumer = $this->container->make(MakeFreshConsumer::class);

		$this->assertNotSame($shared, $consumer->cache);
		$this->assertInstanceOf(ConfigurableCache::class, $consumer->cache);
		$this->assertSame(3600, $consumer->cache->ttl);
		$this->assertSame(60, $shared->ttl);
	}

	public function testMakeFreshAttributeThrowsForAnUnknownAbstract(): void
	{
		$this->expectException(NotFoundException::class);

		$this->container->make(MakeFreshUnknownConsumer::class);
	}
}
