<?php
/**
 * Routing component class.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Routing;

// Contracts.
use Blush\Contracts\Bootable;
use Blush\Contracts\Content\Types;
use Blush\Contracts\Routing\Routes;

// Classes.
use Blush\Config;
use Blush\Controllers;

class Component implements Bootable
{
	/**
	 * Sets up the object state.
	 *
	 * @since 1.0.0
	 */
	public function __construct(
		protected Routes $routes,
		protected Types $types
	) {}

	/**
	 * Bootstraps the component.
	 *
	 * @since 1.0.0
	 */
	public function boot() : void
	{
		// Get the homepage alias if it exists.
		$alias = Config::get( 'app.home_alias' );

		// Sort the content types.
		$types = array_reverse( $this->types->sortByPath() );

		// Loop through the content types and add their routes.
		foreach ( $types as $type ) {

			// Skip if the content type doesn't support routing.
			if ( ! $type->routing() ) {
				continue;
			}

			foreach ( $type->routes() as $uri => $args ) {
				$this->routes->add( $uri, $args );
			}
		}

		// Add homepage feed and paged routes if we have content type alias.
		if ( $alias && $this->types->has( $alias ) ) {

			if ( $this->types->get( $alias )->hasFeed() ) {
				$this->routes->add( 'feed', [
					'name'       => 'home.feed',
					'controller' => Controllers\CollectionFeed::class
				] );
			}

			$this->routes->add( 'page/{page}', [
				'name'       => 'home.paged',
				'controller' => Controllers\Home::class
			] );
		}

		// Add homepage route.
		$this->routes->add( '/', [
			'name'       => 'home',
			'controller' => Controllers\Home::class
		] );

		// Add cache purge route for individual stores.
		$this->routes->add( 'purge/cache/{name}/{key}', [
			'name'       => 'purge.cache.store',
			'controller' => Controllers\Cache::class
		] );

		// Add cache purge route for all stores.
		$this->routes->add( 'purge/cache/{key}', [
			'name'       => 'purge.cache',
			'controller' => Controllers\Cache::class
		] );

		// Add catchall page route.
		$this->routes->add( '{*}', [
			'name'       => 'page.single',
			'controller' => Controllers\SinglePage::class
		] );
	}
}
