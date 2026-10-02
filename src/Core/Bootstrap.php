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

use Blush\Admin\AdminConfig;
use Blush\Auth\AuthConfig;
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
use Blush\Embed\EmbedConfig;
use Blush\Export\ExportConfig;
use Blush\Extension\LocalAutoloader;
use Blush\Feed\FeedConfig;
use Blush\Field\FieldConfig;
use Blush\Http\HttpConfig;
use Blush\Icon\IconConfig;
use Blush\Icon\IconPackCache;
use Blush\Icon\IconPackDiscovery;
use Blush\Icon\IconPacks;
use Blush\Log\LogConfig;
use Blush\Markdown\MarkdownConfig;
use Blush\Media\MediaConfig;
use Blush\Plugin\PluginCache;
use Blush\Plugin\PluginConfig;
use Blush\Plugin\PluginDiscovery;
use Blush\Plugin\PluginManifest;
use Blush\Plugin\Plugins;
use Blush\Preview\PreviewConfig;
use Blush\Publish\PublishConfig;
use Blush\Routing\RouteCache;
use Blush\Routing\RouteConfig;
use Blush\Session\SessionConfig;
use Blush\Settings\SettingsFile;
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
 *    `config/*.php` files, with defaults for anything unconfigured. The
 *    settings saved in the admin (`user/data/settings.json`, D-324) are
 *    laid over it on every build, and never compiled, so a save needs no
 *    compiling.
 * 3. Outside development, the container reads compiled resolution plans.
 * 4. The bootstrap itself, `Paths`, `Env`, the config repository, and
 *    every config object are bound in the container.
 * 5. Plugins are discovered (or read from cache), filtered by config and
 *    the settings, and by their requirements (D-385), and the local ones
 *    that run are autoloaded.
 * 6. Themes and icon packs are discovered (or read from cache), and the
 *    active theme chain's local themes are autoloaded. A theme or icon
 *    pack whose namespace an installed plugin (or, for a pack, a theme)
 *    claims is left out as broken (D-378), and packs turned off
 *    (`IconConfig`, D-385) are kept but add no icons.
 * 7. Providers register in order: framework, plugins, the active theme
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
	 * Compiles the config, plugin, theme, icon pack, route, content type,
	 * and container-plan caches. The routes, content types, and container
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

		new ConfigCache($this->file(CompiledCache::Config))->write($this->config($this->env(), settings: false));
		new PluginCache($this->file(CompiledCache::Plugins))->write($built->plugins->installed());
		new ThemeCache($this->file(CompiledCache::Themes))->write($built->themes);
		new IconPackCache($this->file(CompiledCache::IconPacks))->write($built->iconPacks);

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

		$installed = $this->discoverPlugins($app->environment);
		$plugins   = Plugins::enabled($installed, $config->get(PluginConfig::class));

		[$themes, $iconPacks] = $this->settleNamespaces(
			$installed,
			$this->discoverThemes($app->environment),
			$this->discoverIconPacks($app->environment)
		);
		$iconPacks = $iconPacks->withDisabled($config->get(IconConfig::class)->disabled);

		$themeProviders = [];
		$autoloader     = new LocalAutoloader();
		$autoloader->addPlugins($plugins);

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

		$container->instance(Plugins::class, $plugins);
		$container->instance(Themes::class, $themes);
		$container->instance(IconPacks::class, $iconPacks);
		$container->instance(LocalAutoloader::class, $autoloader);

		$application = new Application($container);
		$application->register(...$plugins->providers(), ...$themeProviders, ...$app->providers);

		return new BootstrapResult($application, $container, $config, $plugins, $autoloader, $themes, $iconPacks);
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
	 * defaults for anything unconfigured, laying the saved settings over
	 * it (unless compiling), and applying the overrides.
	 */
	private function config(Env $env, bool $settings = true): ConfigRepository
	{
		$config = new ConfigCache($this->file(CompiledCache::Config))->read()
			?? new ConfigLoader($env, $this->paths)->load($this->paths->config);

		$config = $config->withDefaults(
			AppConfig::fromEnv($env),
			new LogConfig(),
			new PluginConfig(),
			new IconConfig(),
			new HttpConfig(),
			new RouteConfig(),
			new MarkdownConfig(),
			new ContentConfig(),
			new FieldConfig(),
			new MediaConfig(),
			new ThemeConfig(),
			new FeedConfig(),
			new SitemapConfig(),
			new CacheConfig(),
			PublishConfig::fromEnv($env),
			new ExportConfig(),
			new EmbedConfig(),
			new SessionConfig(),
			new AuthConfig(),
			new AdminConfig(),
			PreviewConfig::fromEnv($env)
		);

		if ($settings) {
			$config = new SettingsFile($this->paths)->read()->apply($config);
		}

		return $config->with(...$this->overrides);
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
	 * Returns every installed plugin: from the cache outside
	 * development when it exists, otherwise by discovery.
	 *
	 * @return list<PluginManifest>
	 */
	private function discoverPlugins(Environment $environment): array
	{
		$cached = $environment->isDevelopment()
			? null
			: new PluginCache($this->file(CompiledCache::Plugins))->read();

		return $cached ?? PluginDiscovery::forPaths($this->paths)->discover();
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
	 * Returns every installed icon pack: from the cache outside
	 * development when it exists, otherwise by discovery.
	 */
	private function discoverIconPacks(Environment $environment): IconPacks
	{
		$cached = $environment->isDevelopment()
			? null
			: new IconPackCache($this->file(CompiledCache::IconPacks))->read();

		return $cached ?? new IconPackDiscovery($this->paths)->discover();
	}

	/**
	 * Leaves out the themes and icon packs whose namespace an extension
	 * before them claims (D-378): installed plugins first, since they're
	 * code a site may depend on, then themes, then icon packs. Each one
	 * left out is recorded as broken, naming the claimant. (Plugins that
	 * share one fail discovery, and themes or packs that do are broken.)
	 *
	 * @param  list<PluginManifest> $plugins
	 * @return array{Themes, IconPacks}
	 */
	private function settleNamespaces(array $plugins, Themes $themes, IconPacks $packs): array
	{
		$claims  = [];
		$reasons = [];

		foreach ($plugins as $plugin) {
			$claims[$plugin->namespace] = "the plugin {$plugin->name}";
		}

		foreach ($themes->all() as $theme) {
			if (isset($claims[$theme->namespace])) {
				$reasons[$theme->name] = [ThemeDiscovery::where($theme, $this->paths), sprintf('Its namespace, "%s", is %s\'s.', $theme->namespace, $claims[$theme->namespace])];
			} else {
				$claims[$theme->namespace] = "the theme {$theme->name}";
			}
		}

		$themes  = $themes->without($reasons);
		$reasons = [];

		foreach ($packs->all() as $pack) {
			if (isset($claims[$pack->namespace])) {
				$reasons[$pack->name] = [IconPackDiscovery::where($pack, $this->paths), sprintf('Its namespace, "%s", is %s\'s.', $pack->namespace, $claims[$pack->namespace])];
			}
		}

		return [$themes, $packs->without($reasons)];
	}

	/**
	 * Returns a compiled cache file under `storage/cache`.
	 */
	private function file(CompiledCache $cache): PhpArrayFile
	{
		return new PhpArrayFile("{$this->paths->cache}/{$cache->value}.php");
	}
}
