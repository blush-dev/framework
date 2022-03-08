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
use Blush\Proxies\Proxy;
use Blush\Proxies\App;
use Blush\Tools\Collection;
use Blush\Tools\Config;

/**
 * Application class.
 *
 * @since  1.0.0
 * @access public
 */
class Application extends Container implements ApplicationContract, Bootable {

	/**
	 * The current version of the framework.
	 *
	 * @since  1.0.0
	 * @access public
	 * @var    string
	 */
	const VERSION = '1.0.0-alpha';

	/**
	 * Array of service provider objects.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    array
	 */
	protected $providers = [];

	/**
	 * Array of static proxy classes and aliases.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    array
	 */
	protected $proxies = [];

	/**
	 * Registers the default bindings, providers, and proxies for the
	 * framework.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function __construct( $path ) {

		$this->instance( 'path', $path );

		$this->registerDefaultBindings();
		$this->registerDefaultProviders();
		$this->registerDefaultProxies();
	}

	/**
	 * Calls the functions to register and boot providers and proxies.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
	public function boot() {
		$this->registerProviders();
		$this->bootProviders();
		$this->registerProxies();
	}

	/**
	 * Registers the default bindings we need to run the framework.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @return void
	 */
	protected function registerDefaultBindings() {

		// Add the instance of this application.
		$this->instance( 'app', $this );

		// Add the version for the framework.
		$this->instance( 'version', static::VERSION );

		// Add default paths.
		$this->instance( 'path.config',    "{$this->path}/config"        );
		$this->instance( 'path.public',    "{$this->path}/public"        );
		$this->instance( 'path.resource',  "{$this->path}/resources"     );
		$this->instance( 'path.storage',   "{$this->path}/storage"       );
		$this->instance( 'path.cache',     "{$this->path}/storage/cache" );
		$this->instance( 'path.user',      "{$this->path}/user"          );
		$this->instance( 'path.content',   "{$this->path}/user/content"  );
		$this->instance( 'path.media',     "{$this->path}/user/media"    );

		// Add config binding.
		$this->instance( 'config', new Collection() );

		// Register each config.
		foreach ( glob( $this->get( 'path.config' ) . '/*.php' ) as $file ) {
			$config = include $file;

			if ( is_array( $config ) ) {
				$this->config->add(
					basename( $file, '.php' ),
					new Config( $config )
				);
			}
		}

		// Add default URIs.
                $this->instance( 'uri',           $this->config->app->uri      );
		$this->instance( 'uri.public',    "{$this->uri}/public"        );
		$this->instance( 'uri.resources', "{$this->uri}/resources"     );
		$this->instance( 'uri.storage',   "{$this->uri}/storage"       );
		$this->instance( 'uri.user',      "{$this->uri}/user"          );
		$this->instance( 'uri.content',   "{$this->uri}/user/content"  );
		$this->instance( 'uri.media',     "{$this->uri}/user/media"    );
	}

	/**
	 * Registers the default service providers.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @return void
	 */
	protected function registerDefaultProviders() {

		// Register framework service providers.
		$this->provider( Providers\App::class      );
		$this->provider( Providers\Content::class  );
		$this->provider( Providers\Markdown::class );
		$this->provider( Providers\Route::class    );

		// Register app service providers.
		$config = $this->config->get( 'app' );

		if ( $config->has( 'providers' ) ) {
			foreach ( (array) $config->get( 'providers' ) as $provider ) {
				$this->provider( $provider );
			}
		}
	}

	/**
	 * Adds the default static proxy classes.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @return void
	 */
	protected function registerDefaultProxies() {
		$this->proxy( App::class, '\Blush\App' );
	}

	/**
	 * Adds a service provider.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  string|object  $provider
	 * @return void
	 */
	public function provider( $provider ) {

		if ( is_string( $provider ) ) {
			$provider = $this->resolveProvider( $provider );
		}

		$this->providers[] = $provider;
	}

	/**
	 * Creates a new instance of a service provider class.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @param  string    $provider
	 * @return object
	 */
	protected function resolveProvider( $provider ) {

		return new $provider( $this );
	}

	/**
	 * Calls a service provider's `register()` method if it exists.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @param  string    $provider
	 * @return void
	 */
	protected function registerProvider( $provider ) {

		if ( method_exists( $provider, 'register' ) ) {
			$provider->register();
		}
	}

	/**
	 * Calls a service provider's `boot()` method if it exists.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @param  string    $provider
	 * @return void
	 */
	protected function bootProvider( $provider ) {

		if ( method_exists( $provider, 'boot' ) ) {
			$provider->boot();
		}
	}

	/**
	 * Returns an array of service providers.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @return array
	 */
	protected function getProviders() {

		return $this->providers;
	}

	/**
	 * Calls the `register()` method of all the available service providers.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @return void
	 */
	protected function registerProviders() {

		foreach ( $this->getProviders() as $provider ) {
			$this->registerProvider( $provider );
		}
	}

	/**
	 * Calls the `boot()` method of all the registered service providers.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @return void
	 */
	protected function bootProviders() {

		foreach ( $this->getProviders() as $provider ) {
			$this->bootProvider( $provider );
		}
	}

	/**
	 * Adds a static proxy alias. Developers must pass in fully-qualified
	 * class name and alias class name.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  string  $class_name
	 * @param  string  $alias
	 * @return void
	 */
	public function proxy( $class_name, $alias ) {

		$this->proxies[ $class_name ] = $alias;
	}

	/**
	 * Registers the static proxy classes.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @return void
	 */
	protected function registerProxies() {

		Proxy::setContainer( $this );

		foreach ( $this->proxies as $class => $alias ) {
			class_alias( $class, $alias );
		}
	}
}
