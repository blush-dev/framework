<?php

/**
 * Application tests.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Tests\Core;

use Psr\Container\ContainerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Blush\Container\ServiceContainer;
use Blush\Core\Application;
use Blush\Core\InvalidProviderException;
use Blush\Tests\Fixtures\Container\NotAProvider;
use Blush\Tests\Fixtures\Container\ProviderA;
use Blush\Tests\Fixtures\Container\ProviderB;
use Blush\Tests\Fixtures\Container\ProviderC;
use Blush\Tests\Fixtures\Container\Recorder;
use Blush\Tests\Fixtures\Container\TestApplication;

#[CoversClass(Application::class)]
final class ApplicationTest extends TestCase
{
	private ServiceContainer $container;
	private Recorder $recorder;
	private TestApplication $app;

	protected function setUp(): void
	{
		$this->container = new ServiceContainer();
		$this->recorder = new Recorder();

		$this->container->instance(Recorder::class, $this->recorder);

		$this->app = new TestApplication($this->container);
	}

	public function testRegistersAllThenBootsInOrder(): void
	{
		$this->app->register(ProviderA::class, ProviderB::class);
		$this->app->boot();

		$this->assertSame(
			['ProviderA:register', 'ProviderB:register', 'ProviderA:boot', 'ProviderB:boot'],
			$this->recorder->events
		);
	}

	public function testLateBatchRegistersAllBeforeBootingAny(): void
	{
		$this->app->register(ProviderA::class);
		$this->app->boot();

		$this->app->register(ProviderB::class, ProviderC::class);

		$this->assertSame(
			[
				'ProviderA:register',
				'ProviderA:boot',
				'ProviderB:register',
				'ProviderC:register',
				'ProviderB:boot',
				'ProviderC:boot'
			],
			$this->recorder->events
		);
	}

	public function testApplicationAndContainerAreBound(): void
	{
		$this->assertSame($this->app, $this->container->get(Application::class));
		$this->assertSame($this->app, $this->container->get(TestApplication::class));
		$this->assertSame($this->container, $this->container->get(ContainerInterface::class));
	}

	public function testProviderRegisteredAfterBootBootsImmediately(): void
	{
		$this->app->boot();
		$this->app->register(ProviderA::class);

		$this->assertSame(
			['ProviderA:register', 'ProviderA:boot'],
			$this->recorder->events
		);
	}

	public function testDuplicateProviderRegistersOnce(): void
	{
		$this->app->register(ProviderA::class);
		$this->app->register(ProviderA::class);
		$this->app->boot();

		$this->assertSame(
			['ProviderA:register', 'ProviderA:boot'],
			$this->recorder->events
		);
	}

	public function testRegisteringANonProviderThrows(): void
	{
		$this->expectException(InvalidProviderException::class);

		$this->app->register(NotAProvider::class);
	}
}
