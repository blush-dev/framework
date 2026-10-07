<?php

/**
 * Application.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Core;

use Override;
use Psr\Container\ContainerInterface;
use Blush\Admin\AdminServiceProvider;
use Blush\Asset\AssetServiceProvider;
use Blush\Auth\AuthServiceProvider;
use Blush\Cache\CacheServiceProvider;
use Blush\Clock\ClockServiceProvider;
use Blush\Console\ConsoleServiceProvider;
use Blush\Container\Container;
use Blush\Container\ContainerException;
use Blush\Container\ServiceResolver;
use Blush\Component\ComponentServiceProvider;
use Blush\Directive\DirectiveServiceProvider;
use Blush\Content\ContentServiceProvider;
use Blush\Core\Events\ApplicationBooted;
use Blush\Data\DataServiceProvider;
use Blush\Error\ErrorServiceProvider;
use Blush\Event\Dispatcher;
use Blush\Event\EventServiceProvider;
use Blush\Embed\EmbedServiceProvider;
use Blush\Icon\IconServiceProvider;
use Blush\Menu\MenuServiceProvider;
use Blush\Region\RegionServiceProvider;
use Blush\Feed\FeedServiceProvider;
use Blush\Http\HttpServiceProvider;
use Blush\Llms\LlmsServiceProvider;
use Blush\Log\LogServiceProvider;
use Blush\Markdown\MarkdownServiceProvider;
use Blush\Media\MediaServiceProvider;
use Blush\Preview\PreviewServiceProvider;
use Blush\Publish\PublishServiceProvider;
use Blush\Session\SessionServiceProvider;
use Blush\Settings\SettingsServiceProvider;
use Blush\Routing\RoutingServiceProvider;
use Blush\Sitemap\SitemapServiceProvider;
use Blush\Theme\ThemeServiceProvider;
use Blush\Translation\TranslationServiceProvider;
use Blush\View\ViewServiceProvider;

/**
 * Wires a project together around a dependency injection container and a set
 * of service providers. The framework's providers (events, clock, log,
 * errors, HTTP, routing, and console) and any listed in a subclass's `PROVIDERS` constant are registered
 * on construction; more are registered at runtime (from extensions, the
 * theme chain, and site config, see `Bootstrap`). `boot()` then boots every
 * registered provider in one pass.
 *
 * Registration always completes before booting: `boot()` boots providers in
 * registration order, and a provider registered after booting has begun is
 * booted immediately, once its batch is registered. Each provider registers
 * and boots only once.
 */
class Application implements Bootable
{
	/**
	 * The framework's own providers, registered before anything else.
	 *
	 * @var list<class-string<ServiceProvider>>
	 */
	private const array CORE_PROVIDERS = [
		EventServiceProvider::class,
		ClockServiceProvider::class,
		LogServiceProvider::class,
		ErrorServiceProvider::class,
		DataServiceProvider::class,
		MarkdownServiceProvider::class,
		ContentServiceProvider::class,
		MediaServiceProvider::class,
		SettingsServiceProvider::class,
		TranslationServiceProvider::class,
		ThemeServiceProvider::class,
		AssetServiceProvider::class,
		ViewServiceProvider::class,
		DirectiveServiceProvider::class,
		ComponentServiceProvider::class,
		FeedServiceProvider::class,
		SitemapServiceProvider::class,
		HttpServiceProvider::class,
		CacheServiceProvider::class,
		PublishServiceProvider::class,
		EmbedServiceProvider::class,
		IconServiceProvider::class,
		MenuServiceProvider::class,
		RegionServiceProvider::class,
		SessionServiceProvider::class,
		AuthServiceProvider::class,
		AdminServiceProvider::class,
		PreviewServiceProvider::class,
		LlmsServiceProvider::class,
		RoutingServiceProvider::class,
		ConsoleServiceProvider::class
	];

	/**
	 * Additional provider class names a subclass registers on
	 * construction, after the framework's.
	 *
	 * @var list<class-string<ServiceProvider>>
	 */
	protected const array PROVIDERS = [];

	/**
	 * Stores the registered service providers, keyed by class name so that
	 * the same provider is never registered more than once.
	 *
	 * @var array<class-string<ServiceProvider>, ServiceProvider>
	 */
	private array $registeredProviders = [];

	/**
	 * Tracks which providers have already been booted, keyed by class name.
	 *
	 * @var array<class-string<ServiceProvider>, true>
	 */
	private array $bootedProviders = [];

	/**
	 * Whether booting has begun. Once it has, a provider registered
	 * afterward is booted as soon as its batch is registered.
	 */
	private bool $booted = false;

	/**
	 * Stores the container and registers the default bindings and service
	 * providers, leaving the application ready to boot.
	 *
	 * @throws ContainerException
	 */
	public function __construct(protected readonly Container $container)
	{
		$this->registerDefaultBindings();
		$this->registerDefaultProviders();
	}

	/**
	 * Registers default container bindings: the container itself, under
	 * its own interface, the resolver interface, and the PSR-11 interface,
	 * and the application.
	 */
	protected function registerDefaultBindings(): void
	{
		$this->container->instance(Container::class, $this->container);
		$this->container->alias(ServiceResolver::class, Container::class);
		$this->container->alias(ContainerInterface::class, Container::class);
		$this->container->instance(self::class, $this);

		if (static::class !== self::class) {
			$this->container->alias(static::class, self::class);
		}
	}

	/**
	 * Registers the default service providers.
	 *
	 * @throws ContainerException
	 */
	protected function registerDefaultProviders(): void
	{
		$this->register(...self::CORE_PROVIDERS, ...static::PROVIDERS);
	}

	/**
	 * Get the container instance.
	 */
	public function container(): Container
	{
		return $this->container;
	}

	/**
	 * Whether booting has begun.
	 */
	public function isBooted(): bool
	{
		return $this->booted;
	}

	/**
	 * Register one or more service providers with the application. A
	 * provider may be passed as an instance or as a class name; class names
	 * are resolved through the container, so providers can type-hint their
	 * own dependencies in the constructor and have them autowired.
	 *
	 * If the application has already booted, the providers in the call are
	 * all registered first and then booted together, so a late batch keeps
	 * the guarantee that every provider is registered before any of them
	 * boots.
	 *
	 * @param  ServiceProvider|class-string ...$providers
	 * @throws ContainerException
	 * @throws InvalidProviderException If a class name is not a `ServiceProvider` subclass.
	 */
	public function register(ServiceProvider|string ...$providers): void
	{
		$registered = [];

		foreach ($providers as $provider) {
			$instance = $this->registerProvider($provider);

			if ($instance !== null) {
				$registered[] = $instance;
			}
		}

		// Once booting has begun, providers registered afterward missed
		// the boot pass. Boot them only after the whole batch is
		// registered, so a provider can rely on the others registered
		// in the same call.
		if ($this->booted) {
			foreach ($registered as $provider) {
				$this->bootProvider($provider);
			}
		}
	}

	/**
	 * Returns the registered providers in registration order.
	 *
	 * @return list<ServiceProvider>
	 */
	public function providers(): array
	{
		return array_values($this->registeredProviders);
	}

	/**
	 * Registers a single provider: validating it, skipping duplicates,
	 * resolving a class name into an instance, and running its `register()`.
	 * Returns the registered provider, or `null` when a provider of that class
	 * is already registered. Booting is left to the caller so a batch can
	 * finish registering before any provider boots.
	 *
	 * @param  ServiceProvider|class-string $provider
	 * @throws ContainerException
	 * @throws InvalidProviderException
	 */
	private function registerProvider(ServiceProvider|string $provider): ?ServiceProvider
	{
		if (is_string($provider) && ! is_subclass_of($provider, ServiceProvider::class)) {
			throw new InvalidProviderException(sprintf(
				'Provider "%s" must be a %s subclass.',
				$provider,
				ServiceProvider::class
			));
		}

		// Determine the provider class up front so a duplicate can be
		// skipped without resolving it from the container.
		$class = is_string($provider) ? $provider : $provider::class;

		if (isset($this->registeredProviders[$class])) {
			return null;
		}

		if (is_string($provider)) {
			$provider = $this->resolveProvider($provider);
		}

		$provider->registerDeclarations();
		$provider->register();
		$this->registeredProviders[$class] = $provider;

		return $provider;
	}

	/**
	 * Resolve a service provider instance from its class name. Resolution
	 * goes through the container, so a provider can type-hint its own
	 * dependencies in the constructor and have them autowired. Override to
	 * customize how providers are constructed.
	 *
	 * @param  class-string<ServiceProvider> $provider
	 * @throws ContainerException
	 */
	protected function resolveProvider(string $provider): ServiceProvider
	{
		return $this->container->make($provider);
	}

	/**
	 * Boots all registered service providers that have not yet been booted
	 * and marks the application as booted, so any provider registered
	 * afterward boots once its batch is registered. When an event
	 * dispatcher is registered, `ApplicationBooted` is dispatched once the
	 * first boot pass completes.
	 *
	 * @throws ContainerException
	 */
	#[Override]
	public function boot(): void
	{
		if ($this->booted) {
			return;
		}

		// Mark booting as begun before the loop so a provider registered
		// during this pass (for example, from another provider's boot())
		// is booted by register() rather than missed by the loop.
		$this->booted = true;

		foreach ($this->registeredProviders as $provider) {
			$this->bootProvider($provider);
		}

		if ($this->container->registered(Dispatcher::class)) {
			$this->container->make(Dispatcher::class)->dispatch(new ApplicationBooted($this));
		}
	}

	/**
	 * Boots a single provider unless it has already been booted.
	 */
	private function bootProvider(ServiceProvider $provider): void
	{
		$class = $provider::class;

		if (isset($this->bootedProviders[$class])) {
			return;
		}

		// Record first, so a provider whose boot registers more
		// providers can't be booted twice through re-entry.
		$this->bootedProviders[$class] = true;

		$provider->bootDeclarations();
		$provider->boot();
	}
}
