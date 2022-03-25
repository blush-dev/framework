<?php
/**
 * Application class.
 *
 * This class is essentially a wrapper around the `Container` class that's
 * specific to the framework. This class is meant to be used as the single,
 * one-true instance of the framework. It's used to load up service providers
 * that interact with the container.
 *
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Core;

use Blush\Container\Container;
use Blush\Contracts\Core\Application as ApplicationContract;
use Blush\Contracts\Bootable;
use Blush\Core\Proxies;
use Blush\Core\Schemas;
use Blush\Messenger\Message;
use Blush\Tools\{Collection, Config, Str};
use Dotenv\Dotenv;
use League\Config\Configuration;

/**
 * Application class.
 *
 * @since  1.0.0
 * @access public
 */
class Application extends Container implements ApplicationContract, Bootable
{
	/**
	 * The current version of the framework.
	 *
	 * @since 1.0.0
	 */
	const VERSION = '1.0.0';

	/**
	 * Array of service provider objects.
	 *
	 * @since 1.0.0
	 */
	protected array $providers = [];

	/**
	 * Array of static proxy classes and aliases.
	 *
	 * @since 1.0.0
	 */
	protected array $proxies = [];

	/**
	 * Registers the default bindings, providers, and proxies for the
	 * framework.
	 *
	 * @since 1.0.0
	 */
	public function __construct( string $path )
	{
		$this->instance( 'path', Str::normalizePath( $path ) );

		$this->registerDefaultConstants();
		$this->registerDefaultBindings();
		$this->registerDefaultProviders();
		$this->registerDefaultProxies();
	}

	/**
	 * Calls the functions to register and boot providers and proxies.
	 *
	 * @since 1.0.0
	 */
	public function boot(): void
	{
		$this->registerProviders();
		$this->registerProxies();
		$this->bootProviders();
	}

	/**
	 * Registers the default constants provided by the framework.
	 *
	 * @since 1.0.0
	 */
	protected function registerDefaultConstants(): void
	{
		define( 'MINUTE_IN_SECONDS',  60                     );
		define( 'HOUR_IN_SECONDS',    60 * MINUTE_IN_SECONDS );
		define( 'DAY_IN_SECONDS',     24 * HOUR_IN_SECONDS   );
		define( 'WEEK_IN_SECONDS',     7 * DAY_IN_SECONDS    );
		define( 'MONTH_IN_SECONDS',   30 * DAY_IN_SECONDS    );
		define( 'YEAR_IN_SECONDS',   365 * DAY_IN_SECONDS    );
	}

	/**
	 * Registers the default bindings we need to run the framework.
	 *
	 * @since 1.0.0
	 */
	protected function registerDefaultBindings(): void
	{
		// Add the instance of this application.
		$this->instance( 'app', $this );

		// Add the version for the framework.
		$this->instance( 'version', static::VERSION );

		// Require the `.env` or `.env.local` file before proceeding.
		if (
			! file_exists( Str::appendPath( $this['path'], '.env' ) ) &&
			! file_exists( Str::appendPath( $this['path'], '.env.local' ) )
		) {
			( new Message() )->make(
				'No <code>.env</code> or <code>.env.local</code> file found for the application. If setting up Blush for the first time, copy and rename the <code>.env.example</code> file.'
			)->dd();
		}

		// Load the dotenv file and parse its data, making it available
		// through the `$_ENV` and `$_SERVER` super-globals.
		Dotenv::createImmutable( $this->path, [ '.env.local', '.env' ] )->load();

		// Creates a new configuration instance and adds the default
		// framework schemas.
		$this->instance( Configuration::class, new Configuration( [
			'app'      => Schemas\App::schema(),
			'cache'    => Schemas\Cache::schema(),
			'content'  => Schemas\Content::schema(),
			'markdown' => Schemas\Markdown::schema()
		] ) );

		// Add alias for configuration.
		$this->alias( Configuration::class, 'config' );

		// Add config path early (cannot change).
		$this->instance( 'path.config', Str::appendPath( $this['path'], 'config' ) );

		// Loop through user-supplied config files and set the data.
		foreach ( [ 'app', 'cache', 'content', 'markdown' ] as $type ) {
			$filepath = Str::appendPath( $this['path.config'], "{$type}.php" );

			if ( file_exists( $filepath ) ) {
				$this['config']->set( $type, include $filepath );
			}
		}

		// Add default paths.
		$this->instance( 'path.app',      $this['path']                                         );
		$this->instance( 'path.public',   Str::appendPath( $this['path'],         'public'    ) );
		$this->instance( 'path.view',     Str::appendPath( $this['path.public'],  'views'     ) );
		$this->instance( 'path.resource', Str::appendPath( $this['path'],         'resources' ) );
		$this->instance( 'path.storage',  Str::appendPath( $this['path'],         'storage'   ) );
		$this->instance( 'path.cache',    Str::appendPath( $this['path.storage'], 'cache'     ) );
		$this->instance( 'path.user',     Str::appendPath( $this['path'],         'user'      ) );
		$this->instance( 'path.content',  Str::appendPath( $this['path.user'],    'content'   ) );
		$this->instance( 'path.media',    Str::appendPath( $this['path.user'],    'media'     ) );

		// Add default URIs.
		$this->instance( 'url',          $this['config']->get( 'app.uri' )                   );
		$this->instance( 'url.app',      $this['url']                                        );
		$this->instance( 'url.config',   Str::appendUri( $this['url'],         'config'    ) );
		$this->instance( 'url.public',   Str::appendUri( $this['url'],         'public'    ) );
		$this->instance( 'url.view',     Str::appendUri( $this['url.public'],  'views'     ) );
		$this->instance( 'url.resource', Str::appendUri( $this['url'],         'resources' ) );
		$this->instance( 'url.storage',  Str::appendUri( $this['url'],         'storage'   ) );
		$this->instance( 'url.cache',    Str::appendUri( $this['url.storage'], 'cache'     ) );
		$this->instance( 'url.user',     Str::appendUri( $this['url'],         'user'      ) );
		$this->instance( 'url.content',  Str::appendUri( $this['url.user'],    'content'   ) );
		$this->instance( 'url.media',    Str::appendUri( $this['url.user'],    'media'     ) );
	}

	/**
	 * Registers the default service providers.
	 *
	 * @since 1.0.0
	 */
	protected function registerDefaultProviders(): void
	{
		// Register framework service providers.
		$this->provider( Providers\App::class      );
		$this->provider( Providers\Cache::class    );
		$this->provider( Providers\Content::class  );
		$this->provider( Providers\Markdown::class );
		$this->provider( Providers\Routing::class  );

		// Register app service providers.
		$providers = $this['config']->get( 'app.providers' );

		foreach ( $providers as $provider ) {
			$this->provider( $provider );
		}
	}

	/**
	 * Adds the default static proxy classes.
	 *
	 * @since 1.0.0
	 */
	protected function registerDefaultProxies(): void
	{
		Proxy::setContainer( $this );

		// Register framework proxies.
		$this->proxy( Proxies\App::class,       '\Blush\App'       );
		$this->proxy( Proxies\Cache::class,     '\Blush\Cache'     );
		$this->proxy( Proxies\Config::class,    '\Blush\Config'    );
		$this->proxy( Proxies\Engine::class,    '\Blush\Engine'    );
		$this->proxy( Proxies\Message::class,   '\Blush\Message'   );
		$this->proxy( Proxies\PoweredBy::class, '\Blush\PoweredBy' );
		$this->proxy( Proxies\Query::class,     '\Blush\Query'     );

		// Register app proxies.
		$proxies = $this['config']->get( 'app.proxies' );

		foreach ( $proxies as $abstract => $proxy ) {
			$this->proxy( $abstract, $proxy );
		}
	}

	/**
	 * Adds a service provider. All service providers must extend the
	 * `ServiceProvider` class. A string or an instance of the provider may
	 * be passed in.
	 *
	 * @since  1.0.0
	 * @param  string|object  $provider
	 */
	public function provider( $provider ): void
	{
		if ( is_string( $provider ) ) {
			$provider = $this->resolveProvider( $provider );
		}

		$this->providers[] = $provider;
	}

	/**
	 * Creates a new instance of a service provider class.
	 *
	 * @since 1.0.0
	 */
	protected function resolveProvider( string $provider ): ServiceProvider
	{
		return new $provider( $this );
	}

	/**
	 * Calls a service provider's `register()` method.
	 *
	 * @since 1.0.0
	 */
	protected function registerProvider( ServiceProvider $provider ): void
	{
		$provider->register();
	}

	/**
	 * Calls a service provider's `boot()` method.
	 *
	 * @since 1.0.0
	 */
	protected function bootProvider( ServiceProvider $provider ): void
	{
		$provider->boot();
	}

	/**
	 * Returns an array of service providers.
	 *
	 * @since 1.0.0
	 */
	protected function getProviders(): array
	{
		return $this->providers;
	}

	/**
	 * Calls the `register()` method of all the available service providers.
	 *
	 * @since 1.0.0
	 */
	protected function registerProviders(): void
	{
		foreach ( $this->getProviders() as $provider ) {
			$this->registerProvider( $provider );
		}
	}

	/**
	 * Calls the `boot()` method of all the registered service providers.
	 *
	 * @since 1.0.0
	 */
	protected function bootProviders(): void
	{
		foreach ( $this->getProviders() as $provider ) {
			$this->bootProvider( $provider );
		}
	}

	/**
	 * Adds a static proxy alias. Developers must pass in fully-qualified
	 * class name and alias class name.
	 *
	 * @since 1.0.0
	 */
	public function proxy( string $class_name, string $alias ): void
	{
		$this->proxies[ $class_name ] = $alias;
	}

	/**
	 * Registers the static proxy classes.
	 *
	 * @since 1.0.0
	 */
	protected function registerProxies(): void
	{
		foreach ( $this->proxies as $class => $alias ) {
			class_alias( $class, $alias );
		}
	}

	/**
	 * Access a keyed path and append a path to it.
	 *
	 * @since  1.0.0
	 */
	public function path( string $accessor = '', string $append = '' ): string
	{
		$path = $accessor ? $this->get( "path.{$accessor}" ) : $this->path;
		return Str::appendPath( $path, $append );
	}

	/**
	 * Returns app path with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	public function app_path( string $append = '' ): string
	{
		return $this->path( 'app', $append );
	}

	/**
	 * Returns config path with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	public function config_path( string $append = '' ): string
	{
		return $this->path( 'config', $append );
	}

	/**
	 * Returns public path with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	public function public_path( string $append = '' ): string
	{
		return $this->path( 'public', $append );
	}

	/**
	 * Returns view path with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	public function view_path( string $append = '' ): string
	{
		return $this->path( 'view', $append );
	}

	/**
	 * Returns resource path with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	public function resource_path( string $append = '' ): string
	{
		return $this->path( 'resource', $append );
	}

	/**
	 * Returns storage path with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	public function storage_path( string $append = '' ): string
	{
		return $this->path( 'storage', $append );
	}

	/**
	 * Returns cache path with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	public function cache_path( string $append = '' ): string
	{
		return $this->path( 'cache', $append );
	}

	/**
	 * Returns user path with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	public function user_path( string $append = '' ): string
	{
		return $this->path( 'user', $append );
	}

	/**
	 * Returns content path with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	public function content_path( string $append = '' ): string
	{
		return $this->path( 'content', $append );
	}

	/**
	 * Returns media path with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	public function media_path( string $append = '' ): string
	{
		return $this->path( 'media', $append );
	}

	/**
	 * Access a keyed URL and append a path to it.
	 *
	 * @since  1.0.0
	 */
	public function url( string $accessor = '', string $append = '' ): string
	{
	        $url = $accessor ? $this->get( "url.{$accessor}" ) : $this->url;
	        return Str::appendUri( $url, $append );
	}

	/**
	 * Returns app URL with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	public function app_url( string $append = '' ): string
	{
	        return $this->url( 'app', $append );
	}

	/**
	 * Returns config URL with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	public function config_url( string $append = '' ): string
	{
	        return $this->url( 'config', $append );
	}

	/**
	 * Returns public URL with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	public function public_url( string $append = '' ): string
	{
	        return $this->url( 'public', $append );
	}

	/**
	 * Returns view URL with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	public function view_url( string $append = '' ): string
	{
	        return $this->url( 'view', $append );
	}

	/**
	 * Returns resource URL with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	public function resource_url( string $append = '' ): string
	{
	        return $this->url( 'resource', $append );
	}

	/**
	 * Returns storage URL with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	public function storage_url( string $append = '' ): string
	{
	        return $this->url( 'storage', $append );
	}

	/**
	 * Returns cache URL with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	public function cache_url( string $append = '' ): string
	{
	        return $this->url( 'cache', $append );
	}

	/**
	 * Returns user URL with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	public function user_url( string $append = '' ): string
	{
	        return $this->url( 'user', $append );
	}

	/**
	 * Returns content URL with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	public function content_url( string $append = '' ): string
	{
	        return $this->url( 'content', $append );
	}

	/**
	 * Returns media URL with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	public function media_url( string $append = '' ): string
	{
	        return $this->url( 'media', $append );
	}
}
