<?php

/**
 * Fixture site app config.
 */

declare(strict_types=1);

use Blush\Core\AppConfig;
use Blush\Core\Environment;
use Blush\Env\Env;
use Blush\Tests\Fixtures\Extension\SiteServiceProvider;

/** @var Env $env */

return new AppConfig(
	name: $env->string('APP_NAME'),
	url: $env->string('APP_URL'),
	environment: Environment::fromString($env->string('APP_ENV', 'production')),
	debug: $env->bool('APP_DEBUG', false),
	timezone: 'America/Chicago',
	providers: [SiteServiceProvider::class]
);
