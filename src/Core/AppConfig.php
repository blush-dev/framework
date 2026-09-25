<?php

/**
 * Application config.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Core;

use DateTimeZone;
use Override;
use Uri\Rfc3986\Uri;
use Blush\Config\Config;
use Blush\Config\ConfigValues;
use Blush\Config\InvalidConfig;
use Blush\Env\Env;

/**
 * Site-wide application settings, from `config/app.php`. When a site has no
 * app config, `fromEnv()` builds one from the `APP_*` variables.
 */
final readonly class AppConfig implements Config
{
	/**
	 * @param list<class-string<ServiceProvider>> $providers The site's own service providers.
	 * @throws InvalidConfig
	 */
	public function __construct(
		public string $name = 'Blush',
		public string $url = 'http://localhost',
		public Environment $environment = Environment::Production,
		public bool $debug = false,
		public string $timezone = 'UTC',
		public string $locale = 'en_US',
		public array $providers = []
	) {
		if (Uri::parse($url) === null || ! preg_match('#^https?://#i', $url)) {
			throw new InvalidConfig(sprintf('AppConfig "url" must be an absolute http(s) URL; "%s" given.', $url));
		}

		if (! in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
			throw new InvalidConfig(sprintf('AppConfig "timezone" "%s" is not a valid timezone.', $timezone));
		}

		foreach ($providers as $provider) {
			if (! is_subclass_of($provider, ServiceProvider::class)) {
				throw new InvalidConfig(sprintf(
					'AppConfig "providers" must list %s subclasses; "%s" is not one.',
					ServiceProvider::class,
					$provider
				));
			}
		}
	}

	/**
	 * Builds the config from `APP_NAME`, `APP_URL`, `APP_ENV`, `APP_DEBUG`,
	 * `APP_TIMEZONE`, and `APP_LOCALE`, with the constructor's defaults
	 * for anything unset.
	 *
	 * @throws InvalidConfig
	 */
	public static function fromEnv(Env $env): self
	{
		return new self(
			name: $env->string('APP_NAME', 'Blush'),
			url: $env->string('APP_URL', 'http://localhost'),
			environment: Environment::fromString($env->string('APP_ENV', 'production')),
			debug: $env->bool('APP_DEBUG', false),
			timezone: $env->string('APP_TIMEZONE', 'UTC'),
			locale: $env->string('APP_LOCALE', 'en_US')
		);
	}

	/**
	 * Returns the timezone as an object.
	 */
	public function timezone(): DateTimeZone
	{
		return new DateTimeZone($this->timezone);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public static function fromArray(array $data): static
	{
		$values = new ConfigValues($data, self::class);
		$values->assertKnownKeys(['name', 'url', 'environment', 'debug', 'timezone', 'locale', 'providers']);

		$environment = $data['environment'] ?? null;

		/** @var list<class-string<ServiceProvider>> $providers Validated by the constructor. */
		$providers = $values->stringList('providers');

		return new static(
			name: $values->string('name', 'Blush'),
			url: $values->string('url', 'http://localhost'),
			environment: is_string($environment) && Environment::tryFrom($environment) === null
				? Environment::fromString($environment)
				: $values->enum('environment', Environment::class, Environment::Production),
			debug: $values->bool('debug', false),
			timezone: $values->string('timezone', 'UTC'),
			locale: $values->string('locale', 'en_US'),
			providers: $providers
		);
	}

	/**
	 * @inheritDoc
	 */
	#[Override]
	public function toArray(): array
	{
		return [
			'name'        => $this->name,
			'url'         => $this->url,
			'environment' => $this->environment,
			'debug'       => $this->debug,
			'timezone'    => $this->timezone,
			'locale'      => $this->locale,
			'providers'   => $this->providers
		];
	}
}
