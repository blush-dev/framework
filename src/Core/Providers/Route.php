<?php
/**
 * Route service provider.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Core\Providers;

use Blush\Core\ServiceProvider;
use Blush\Controllers;
use Blush\Routing\Routes;
use Blush\Routing\Router;

class Route extends ServiceProvider {

	/**
	 * Register bindings.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
        public function register() {
                $this->app->instance( 'routes', new Routes() );
                $this->app->instance( 'router', new Router( $this->app->routes ) );
        }

	/**
	 * Bootstrap bindings.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return void
	 */
        public function boot() {
		$types = $this->app->resolve( 'content/types' );
		$types = array_reverse( $types->sortByPath() );

		foreach ( (array) $types as $type ) {

			$this->app->routes->get(
				$type->path() . '/page/{number}',
				Controllers\ContentTypeArchive::class
			);

			if ( $type->isTaxonomy() ) {
				$this->app->routes->get(
					$type->path() . '/{name}/page/{number}',
					Controllers\TaxonomyTerm::class
				);
			}

			$this->app->routes->get(
				$type->path() . '/{name}',
				$type->isTaxonomy()
					? Controllers\TaxonomyTerm::class
					: Controllers\Single::class
			);

			$this->app->routes->get(
				$type->path(),
				Controllers\ContentTypeArchive::class
			);
		}

		$this->app->routes->get( '/',                 Controllers\Home::class    );
                $this->app->routes->get( 'cache/purge/{key}', Controllers\Cache::class   );
                $this->app->routes->get( '{name}',            Controllers\SinglePage::class );
        }
}
