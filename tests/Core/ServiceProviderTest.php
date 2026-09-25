<?php

/**
 * Service provider tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Core;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Container\ServiceContainer;
use Blush\Core\ServiceProvider;
use Blush\Core\UnbootableServiceException;
use Blush\Tests\Fixtures\Container\BindingProvider;
use Blush\Tests\Fixtures\Container\BootableProvider;
use Blush\Tests\Fixtures\Container\Cache;
use Blush\Tests\Fixtures\Container\FileCache;
use Blush\Tests\Fixtures\Container\OverridableProvider;
use Blush\Tests\Fixtures\Container\Recorder;
use Blush\Tests\Fixtures\Container\SharedService;
use Blush\Tests\Fixtures\Container\TransientService;
use Blush\Tests\Fixtures\Container\UnbootableProvider;

#[CoversClass(ServiceProvider::class)]
final class ServiceProviderTest extends TestCase
{
	private ServiceContainer $container;

	protected function setUp(): void
	{
		$this->container = new ServiceContainer();
	}

	public function testSingletonsConstantRegistersSharedBindings(): void
	{
		(new BindingProvider($this->container))->registerDeclarations();

		$this->assertInstanceOf(FileCache::class, $this->container->get(Cache::class));
		$this->assertSame(
			$this->container->get(Cache::class),
			$this->container->get(Cache::class)
		);
	}

	public function testSingletonsConstantSelfBindsNumericEntries(): void
	{
		(new BindingProvider($this->container))->registerDeclarations();

		$this->assertSame(
			$this->container->get(SharedService::class),
			$this->container->get(SharedService::class)
		);
	}

	public function testTransientsConstantRegistersFreshBindings(): void
	{
		(new BindingProvider($this->container))->registerDeclarations();

		$this->assertNotSame(
			$this->container->get(TransientService::class),
			$this->container->get(TransientService::class)
		);
	}

	public function testAliasesConstantRegistersAliases(): void
	{
		(new BindingProvider($this->container))->registerDeclarations();

		$this->assertSame(
			$this->container->get(Cache::class),
			$this->container->get('cache.alias')
		);
	}

	public function testTagsConstantAssignsTags(): void
	{
		(new BindingProvider($this->container))->registerDeclarations();

		$this->assertCount(2, $this->container->tagged('group'));
	}

	public function testSingletonIfLeavesAnExistingBinding(): void
	{
		$this->container->singleton(Cache::class, FileCache::class);

		(new OverridableProvider($this->container))->registerDeclarations();

		$this->assertInstanceOf(FileCache::class, $this->container->get(Cache::class));
	}

	public function testTransientIfLeavesAnExistingBinding(): void
	{
		$this->container->singleton(TransientService::class);

		(new OverridableProvider($this->container))->registerDeclarations();

		$this->assertSame(
			$this->container->get(TransientService::class),
			$this->container->get(TransientService::class)
		);
	}

	public function testBootableConstantBootsInDeclarationOrder(): void
	{
		$recorder = new Recorder();
		$this->container->instance(Recorder::class, $recorder);

		(new BootableProvider($this->container))->bootDeclarations();

		$this->assertSame(['first', 'second'], $recorder->events);
	}

	public function testBootableThrowsWhenServiceIsNotBootable(): void
	{
		$this->expectException(UnbootableServiceException::class);

		(new UnbootableProvider($this->container))->bootDeclarations();
	}
}
