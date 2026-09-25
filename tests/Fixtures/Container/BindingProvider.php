<?php

declare(strict_types=1);

namespace Blush\Tests\Fixtures\Container;

use Blush\Core\ServiceProvider;

final class BindingProvider extends ServiceProvider
{
	protected const array SINGLETONS = [
		SharedService::class,
		Cache::class => FileCache::class
	];

	protected const array TRANSIENTS = [
		TransientService::class
	];

	protected const array ALIASES = [
		'cache.alias' => Cache::class
	];

	protected const array TAGS = [
		'group' => [SharedService::class, TransientService::class]
	];
}
