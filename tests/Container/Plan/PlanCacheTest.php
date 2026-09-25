<?php

/**
 * Plan cache tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Container\Plan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Container\Plan\ClassPlan;
use Blush\Container\Plan\CompiledPlanner;
use Blush\Container\Plan\PlanCache;
use Blush\Container\Plan\ReflectionPlanner;
use Blush\Container\ServiceContainer;
use Blush\Support\PhpArrayFile;
use Blush\Tests\Fixtures\Container\Cache;
use Blush\Tests\Fixtures\Container\FileCache;
use Blush\Tests\Fixtures\Container\Plan\ClosureCondition;
use Blush\Tests\Fixtures\Container\Plan\ObjectDefault;
use Blush\Tests\Fixtures\Container\Plan\Tagged;
use Blush\Tests\Fixtures\Container\SharedService;
use Blush\Tests\Fixtures\Container\Status;
use Blush\Tests\TemporaryDirectory;

#[CoversClass(PlanCache::class)]
#[CoversClass(CompiledPlanner::class)]
#[CoversClass(ReflectionPlanner::class)]
#[CoversClass(ClassPlan::class)]
final class PlanCacheTest extends TestCase
{
	use TemporaryDirectory;

	private PlanCache $cache;

	protected function setUp(): void
	{
		$this->cache = new PlanCache(new PhpArrayFile($this->temporaryDirectory() . '/container.php'));
	}

	public function testPlannerWithoutACompiledFileReflects(): void
	{
		$this->assertInstanceOf(ReflectionPlanner::class, $this->cache->planner());
	}

	public function testCompiledPlanRoundTripsUnchanged(): void
	{
		$planner = new ReflectionPlanner();
		$plan    = $planner->forClass(Tagged::class);

		$this->assertSame(1, $this->cache->write([$plan]));

		$this->assertEquals(
			$plan->toArray(),
			$this->cache->planner()->forClass(Tagged::class)->toArray()
		);
	}

	public function testNonExportablePlansAreSkipped(): void
	{
		$planner = new ReflectionPlanner();

		$written = $this->cache->write([
			$planner->forClass(Tagged::class),
			$planner->forClass(ObjectDefault::class),
			$planner->forClass(ClosureCondition::class)
		]);

		$this->assertSame(1, $written);
		$this->assertFalse($planner->forClass(ObjectDefault::class)->isExportable());
		$this->assertFalse($planner->forClass(ClosureCondition::class)->isExportable());
	}

	public function testContainerResolvesFromCompiledPlans(): void
	{
		$warm = new ReflectionPlanner();
		$warm->forClass(Tagged::class);
		$this->cache->write($warm->plans());

		$container = new ServiceContainer($this->cache->planner());
		$container->singleton(Cache::class, FileCache::class);
		$container->tagFromAttributes(Tagged::class);

		$tagged = $container->make(Tagged::class);

		$this->assertInstanceOf(FileCache::class, $tagged->cache);
		$this->assertSame(Status::Active, $tagged->status);
		$this->assertSame(10, $tagged->limit);
		$this->assertSame($tagged, $container->make(Tagged::class));
		$this->assertSame(['tagged' => Tagged::class], $container->taggedAbstractsWith('plans', 'slug'));
	}

	public function testCompiledPlansAreUsedInsteadOfReflection(): void
	{
		// The class declares no #[Singleton]; the compiled plan says it
		// does. Sharing the instance proves the plan, not reflection, was
		// used.
		$plan = new ClassPlan(class: SharedService::class, singleton: true);

		$container = new ServiceContainer(new CompiledPlanner([SharedService::class => $plan->toArray()]));

		$this->assertSame(
			$container->make(SharedService::class),
			$container->make(SharedService::class)
		);
	}

	public function testCompiledPlannerFallsBackForUnknownClasses(): void
	{
		$container = new ServiceContainer(new CompiledPlanner([]));
		$container->singleton(Cache::class, FileCache::class);

		$this->assertInstanceOf(FileCache::class, $container->make(Tagged::class)->cache);
	}

	public function testObjectDefaultsAreBuiltFreshForEachInstance(): void
	{
		$container = new ServiceContainer();

		$this->assertNotSame(
			$container->make(ObjectDefault::class)->cache,
			$container->make(ObjectDefault::class)->cache
		);
	}

	public function testClosureConditionsStillWork(): void
	{
		$container = new ServiceContainer();

		$this->assertSame(
			$container->make(ClosureCondition::class),
			$container->make(ClosureCondition::class)
		);
	}

	public function testClearRemovesTheCompiledFile(): void
	{
		$this->cache->write([new ReflectionPlanner()->forClass(Tagged::class)]);
		$this->cache->clear();

		$this->assertInstanceOf(ReflectionPlanner::class, $this->cache->planner());
	}
}
