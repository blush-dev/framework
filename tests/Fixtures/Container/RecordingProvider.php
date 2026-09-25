<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Container;

use ReflectionClass;
use Blush\Core\ServiceProvider;

abstract class RecordingProvider extends ServiceProvider
{
	public function register(): void
	{
		$this->record('register');
	}

	public function boot(): void
	{
		$this->record('boot');
	}

	private function record(string $phase): void
	{
		$name = (new ReflectionClass($this))->getShortName();

		$this->container->make(Recorder::class)->events[] = "{$name}:{$phase}";
	}
}
