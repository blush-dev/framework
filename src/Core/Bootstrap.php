<?php

/**
 * Bootstrap.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2026, Justin Tadlock
 * @license   https://opensource.org/licenses/MIT MIT
 * @link      https://github.com/blush-dev/framework
 */

declare(strict_types=1);

namespace Blush\Core;

use Blush\Cache\CacheConfig;
use Blush\Config\Config;
use Blush\Config\ConfigCache;
use Blush\Config\ConfigLoader;
use Blush\Config\ConfigRepository;
use Blush\Container\Plan\PlanCache;
use Blush\Container\Plan\Planner;
use Blush\Container\Plan\ReflectionPlanner;
use Blush\Container\ServiceContainer;
use Blush\Content\Type\ContentConfig;
use Blush\Content\Type\ContentTypeCache;
use Blush\Env\Env;
use Blush\Export\ExportConfig;
use Blush\Extension\ExtensionCache;
use Blush\Extension\ExtensionConfig;
use Blush\Extension\ExtensionDiscovery;
use Blush\Extension\ExtensionManifest;
use Blush\Extension\Extensions;
use Blush\Extension\LocalAutoloader;
use Blush\Feed\FeedConfig;
use Blush\Http\HttpConfig;
use Blush\Log\LogConfig;
use Blush\Markdown\MarkdownConfig;
use Blush\Media\MediaConfig;
use Blush\Publish\PublishConfig;
use Blush\Routing\RouteCache;
use Blush\Routing\RouteConfig;
use Blush\Sitemap\SitemapConfig;
use Blush\Support\PhpArrayFile;
use Blush\Theme\ThemeCache;
use Blush\Theme\ThemeConfig;
use Blush\Theme\ThemeDiscovery;
use Blush\Theme\ThemeException;
use Blush\Theme\Themes;

/**
 * Builds a site's application from its project root. This is the one place
 * the pieces of the core meet:
 *
 * 1. `.env` is loaded under the process environment.
 * 2. Config comes from the compiled cache when present, otherwise from the
 *    `config/*.php` files, with defaults for anything unconfigured.
 * 3. Outside development, the container reads compiled resolution plans.
 * 4. The bootstrap itself, `Paths`, `Env`, the config repository, and
 *    every config object are bound in the container.
 * 5. Extensions are discovered (or read from cache), filtered by config, and
 *    local ones are autoloaded.
 * 6. Themes are discovered (or read from cache), and the active theme
 *    chain's local themes are autoloaded.
 * 7. Providers register in order: framework, extensions, the active theme
 *    chain's (ancestors first), then the site's (D-054). A broken theme
 *    chain registers no theme providers, so the CLI still runs to fix it;
 *    rendering reports the problem.
 *
 * The application is returned registered but not booted. `withConfig()`
 * and `withPaths()` return a bootstrap for a variant of the site, such as
 * the production application static export renders with (D-135).
 */
final readonly class Bootstrap
{
	/**
	 * @param array<string, string> $environment The process environment.
	 * @param list<Config>          $overrides   Config objects that replace the site's.
	 */
	public function __construct(
		private Paths $paths,
		private array $environment = [],
		private array $overrides = []
	) {
	}

	/**
	 * Returns a copy whose applications use these config objects in place
	 * of the site's (or the compiled cache's) of the same class.
	 */
	public function withConfig(Config ...$configs): self
	{
		return new self($this->paths, $this->environment, [...$this->overrides, ...array_values($configs)]);
	}

	/**
	 * Returns a copy for other paths.
	 */
	public function withPaths(Paths $paths): self
	{
		return new self($paths, $this->environment, $this->overrides);
	}

	/**
	 * Builds a bootstrap for a project root with the real process
	 * environment. `$paths` overrides locations (such as `public`).
	 *
	 * @param array<string, string> $paths
	 */
	public static function fromRoot(string $root, array $paths = []): self
	{
		return new self(Paths::fromRoot($root, $paths), getenv());
	}

	/**
	 * Builds the application.
	 */
	public function createApplication(): Application
	{
		return $this->build($this->plannerFor(...))->application;
	}

	/**
	 * Compiles the config, extension, theme, route, content type, and
	 * container-plan caches. The routes, content types, and container
	 * plans are gathered by booting a fresh application, so every
	 * provider and bootable service gets planned, and then by planning
	 * every class the booted container knows about and every route's
	 * controller, along with their dependencies (D-066).
	 * Returns the number of plans compiled.
	 */
	public function compile(): int
	{
		$this->clearCompiled();

		$planner = new ReflectionPlanner();
		$built   = $this->build(static fn (): Planner => $planner);

		new ConfigCache($this->file(CompiledCache::Config))->write($built->config);
		new ExtensionCache($this->file(CompiledCache::Extensions))->write($built->extensions->all());
		new ThemeCache($this->file(CompiledCache::Themes))->write($built->themes);

		$built->application->boot();

		$routes = $built->container->make(RouteCache::class)->write();
		$built->container->make(ContentTypeCache::class)->write();

		$planner->warm([...$built->container->knownClasses(), ...$routes->controllers()]);
		$built->autoloader->unregister();

		return new PlanCache($this->file(CompiledCache::Container))->write($planner->plans());
	}

	/**
	 * Deletes the given compiled caches, or all of them when none are
	 * given.
	 */
	public function clearCompiled(CompiledCache ...$caches): void
	{
		foreach ($caches === [] ? CompiledCache::cases() : $caches as $cache) {
			$this->file($cache)->delete();
		}
	}

	/**
	 * Returns the path of a compiled cache file.
	 */
	public function compiledPath(CompiledCache $cache): string
	{
		return $this->file($cache)->path;
	}

	/**
	 * Builds the application with the planner the callback picks for the
	 * environment.
	 *
	 * @param callable(Environment): Planner $planner
	 */
	private function build(callable $planner): BootstrapResult
	{
		$env    = $this->env();
		$config = $this->config($env);
		$app    = $config->get(AppConfig::class);

		$container = new ServiceContainer($planner($app->environment));

		$container->instance(self::class, $this);
		$container->instance(Paths::class, $this->paths);
		$container->instance(Env::class, $env);
		$container->instance(ConfigRepository::class, $config);

		foreach ($config->all() as $object) {
			$container->instance($object::class, $object);
		}

		$extensions = Extensions::enabled(
			$this->discoverExtensions($app->environment),
			$config->get(ExtensionConfig::class)
		);

		$themes         = $this->discoverThemes($app->environment);
		$themeProviders = [];
		$autoloader     = new LocalAutoloader();
		$autoloader->addExtensions($extensions);

		try {
			$chain = $themes->chain($config->get(ThemeConfig::class)->active);
			$autoloader->addThemes($chain);
		} catch (ThemeException) {
			// Reported when a page renders, and by `theme:check`.
			$chain = null;
		}

		$autoloader->register();

		// A provider that isn't a service provider is skipped here and
		// reported by `theme:check`.
		foreach ($chain?->providers() ?? [] as $provider) {
			if (is_subclass_of($provider, ServiceProvider::class)) {
				$themeProviders[] = $provider;
			}
		}

		$container->instance(Extensions::class, $extensions);
		$container->instance(Themes::class, $themes);
		$container->instance(LocalAutoloader::class, $autoloader);

		$application = new Application($container);
		$application->register(...$extensions->providers(), ...$themeProviders, ...$app->providers);

		return new BootstrapResult($application, $container, $config, $extensions, $autoloader, $themes);
	}

	/**
	 * Loads `.env`.
	 */
	private function env(): Env
	{
		return Env::load($this->paths->root . '/.env', $this->environment);
	}

	/**
	 * Returns the compiled config, or loads the config files, filling in
	 * defaults for anything unconfigured and applying the overrides.
	 */
	private function config(Env $env): ConfigRepository
	{
		$config = new ConfigCache($this->file(CompiledCache::Config))->read()
			?? new ConfigLoader($env, $this->paths)->load($this->paths->config);

		return $config->withDefaults(
			AppConfig::fromEnv($env),
			new LogConfig(),
			new ExtensionConfig(),
			new HttpConfig(),
			new RouteConfig(),
			new MarkdownConfig(),
			new ContentConfig(),
			new MediaConfig(),
			new ThemeConfig(),
			new FeedConfig(),
			new SitemapConfig(),
			new CacheConfig(),
			PublishConfig::fromEnv($env),
			new ExportConfig()
		)->with(...$this->overrides);
	}

	/**
	 * Returns the container planner for the environment: compiled plans
	 * everywhere but development, where code changes constantly.
	 */
	private function plannerFor(Environment $environment): Planner
	{
		return $environment->isDevelopment()
			? new ReflectionPlanner()
			: new PlanCache($this->file(CompiledCache::Container))->planner();
	}

	/**
	 * Returns every installed extension: from the cache outside
	 * development when it exists, otherwise by discovery.
	 *
	 * @return list<ExtensionManifest>
	 */
	private function discoverExtensions(Environment $environment): array
	{
		$cached = $environment->isDevelopment()
			? null
			: new ExtensionCache($this->file(CompiledCache::Extensions))->read();

		return $cached ?? ExtensionDiscovery::forPaths($this->paths)->discover();
	}

	/**
	 * Returns every installed theme: from the cache outside development
	 * when it exists, otherwise by discovery.
	 */
	private function discoverThemes(Environment $environment): Themes
	{
		$cached = $environment->isDevelopment()
			? null
			: new ThemeCache($this->file(CompiledCache::Themes))->read();

		return $cached ?? new ThemeDiscovery($this->paths)->discover();
	}

	/**
	 * Returns a compiled cache file under `storage/cache`.
	 */
	private function file(CompiledCache $cache): PhpArrayFile
	{
		return new PhpArrayFile("{$this->paths->cache}/{$cache->value}.php");
	}
}
