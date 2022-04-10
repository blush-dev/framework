<?php
/**
 * Content type.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Content\Types;

use Blush\Contracts\Content\Type as TypeContract;
use Blush\{Config, Url};
use Blush\Controllers;
use Blush\Tools\Str;

class Type implements TypeContract
{
	/**
	 * Content type path.
	 *
	 * @since 1.0.0
	 */
	protected string $path = '';

	/**
	 * Array of content type routes.
	 *
	 * @since 1.0.0
	 */
	protected array $routes = [];

	/**
	 * Whether routing should be enabled for this post type. Mostly for
	 * internal use with pages.
	 *
	 * @since 1.0.0
	 */
	protected bool $routing = true;

	/**
	 * Whether the content type is a taxonomy.
	 *
	 * @since 1.0.0
	 */
	protected bool $taxonomy = false;

	/**
	 * The content type that this content type collects in archives. By
	 * default, content types will collect themselves.
	 *
	 * @since 1.0.0
	 */
	protected string|bool|null $collect = null;

	/**
	 * If the content type is a taxonomy, the content type that the
	 * taxonomy terms collect.
	 *
	 * @since 1.0.0
	 */
	protected string|bool|null $term_collect = null;

	/**
	 * Array of Query args when type is called as a collection.
	 *
	 * @since 1.0.0
	 */
	protected array $collection = [];

	/**
	 * Array of Query args when a taxonomy term is called as a collection.
	 *
	 * @since 1.0.0
	 */
	protected array $term_collection = [];

	/**
	 * Whether to generate a feed for the content type.
	 *
	 * @since 1.0.0
	 */
	protected bool|array $feed = false;

	/**
	 * Whether to generate date-based archives for the content type.
	 *
	 * @since 1.0.0
	 */
	protected bool $date_archives = false;

	/**
	 * Whether to generate time-based archives for the content type.
	 *
	 * @since 1.0.0
	 */
	protected bool $time_archives = false;

	/**
	 * Stores an array of the URL paths.
	 *
	 * @since 1.0.0
	 */
	protected array $url_paths = [];

	/**
	 * Sets up the object state.
	 *
	 * @since 1.0.0
	 */
	public function __construct( protected string $type, array $options = [] )
	{
		// Set up the object properties based on parameters.
		$this->path            = $options['path']            ?? $type;
		$this->collection      = $options['collection']      ?? [];
		$this->routes          = $options['routes']          ?? [];
		$this->routing         = $options['routing']         ?? true;
		$this->feed            = $options['feed']            ?? false;
		$this->date_archives   = $options['date_archives']   ?? false;
		$this->time_archives   = $options['time_archives']   ?? false;
		$this->taxonomy        = $options['taxonomy']        ?? false;
		$this->term_collect    = $options['term_collect']    ?? null;
		$this->term_collection = $options['term_collection'] ?? [];

		// Unless the `collect` option is explicitly set to `false`,
		// it should at least collect itself.
		if ( false !== $options['collect'] ) {
			$this->collect = $options['collect'] ?: $type;
		}

		// Build the collection path.
		$collection = $options['url_paths']['collection']
			?? $options['uri']
			?? $this->path;

		// Build the single path.
		$single = $options['url_paths']['single']
			?? $options['uri_single']
			?? "{$collection}/{name}";

		// Merge the user-configured URL paths with the defaults.
		$this->url_paths = array_merge( [
			'collection'              => $collection,
			'single'                  => $single,
			'single.paged'            => "{$single}/page/{page}",
			'collection.feed'         => "{$collection}/feed",
			'collection.paged'        => "{$collection}/page/{page}",
			'collection.second.paged' => "{$collection}/{year}/{month}/{day}/{hour}/{minute}/{second}/page/{page}",
			'collection.second'       => "{$collection}/{year}/{month}/{day}/{hour}/{minute}/{second}",
			'collection.minute.paged' => "{$collection}/{year}/{month}/{day}/{hour}/{minute}/page/{page}",
			'collection.minute'       => "{$collection}/{year}/{month}/{day}/{hour}/{minute}",
			'collection.hour.paged'   => "{$collection}/{year}/{month}/{day}/{hour}/page/{page}",
			'collection.hour'         => "{$collection}/{year}/{month}/{day}/{hour}",
			'collection.day.paged'    => "{$collection}/{year}/{month}/{day}/page/{page}",
			'collection.day'          => "{$collection}/{year}/{month}/{day}",
			'collection.month.paged'  => "{$collection}/{year}/{month}/page/{page}",
			'collection.month'        => "{$collection}/{year}/{month}",
			'collection.year.paged'   => "{$collection}/{year}/page/{page}",
			'collection.year'         => "{$collection}/{year}"
		], $options['url_paths'] ?? [] );

		// Check if user passed in a set of custom routes.
		if ( isset( $options['routes'] ) && is_array( $options['routes'] ) ) {

			// Loop through each route and add it to routes array.
			foreach ( $options['routes'] as $route => $args ) {

				// If args is a string, make it the controller.
				if ( is_string( $args ) ) {
					$args = [ 'controller' => $args ];
				}

				// Add the route to the routes array.
				$this->routes[ $route ] = $args;
			}
		}
	}

	/**
	 * Returns the content type name (alias for `type()`).
	 *
	 * @since 1.0.0
	 */
	public function name(): string
	{
		return $this->type();
	}

	/**
	 * Returns the content type name.
	 *
	 * @since 1.0.0
	 */
	public function type(): string
	{
		return $this->type;
	}

	/**
	 * Conditional check if the typs is the homepage alias.
	 *
	 * @since 1.0.0
	 */
	public function isHomeAlias(): bool
	{
		return $this->type() === Config::get( 'app.home_alias' );
	}

	/**
	 * Returns the content type path.
	 *
	 * @since 1.0.0
	 */
	public function path(): string
	{
		return $this->path;
	}

	/**
	 * Returns a keyed URL path.
	 *
	 * @since 1.0.0
	 */
	public function urlPath( string $key = '' ): string
	{
		if ( ! $key ) {
			return $this->url_paths['collection'];
		}

		return $this->url_paths[ $key ] ?? '';
	}

	/**
	 * Returns the content type URL.
	 *
	 * @since 1.0.0
	 */
	public function url(): string
	{
		return $this->isHomeAlias()
		       ? Url::route( '/' )
		       : Url::route( $this->urlPath( 'collection' ) );
	}

	/**
	 * Returns the content type URL for single entries.
	 *
	 * @since 1.0.0
	 */
	public function singleUrl( array $params = [] ): string
	{
		return Url::route( $this->urlPath( 'single' ), $params );
	}

	/**
	 * Returns the content type feed URL.
	 *
	 * @since 1.0.0
	 */
	public function feedUrl(): string
	{
		return $this->isHomeAlias()
		       ? Url::route( 'feed' )
		       : Url::route( $this->urlPath( 'collection.feed' ) );
	}

	/**
	 * Returns the content type URL for year archives.
	 *
	 * @since 1.0.0
	 */
	public function yearUrl( string $year ): string
	{
		return Url::route( $this->urlPath( 'collection.year' ), [
			'year' => $year
		] );
	}

	/**
	 * Returns the content type URL for month archives.
	 *
	 * @since 1.0.0
	 */
	public function monthUrl( string $year, string $month ): string
	{
		return Url::route( $this->urlPath( 'collection.month' ), [
			'year'  => $year,
			'month' => $month
		] );
	}

	/**
	 * Returns the content type URI for day archives.
	 *
	 * @since 1.0.0
	 */
	public function dayUrl( string $year, string $month, string $day ): string
	{
		return Url::route( $this->urlPath( 'collection.day' ), [
			'year'  => $year,
			'month' => $month,
			'day'   => $day
		] );
	}

	/**
	 * Whether the content type should have a feed.
	 *
	 * @since 1.0.0
	 */
	public function hasFeed(): bool
	{
		return false !== $this->feed;
	}

	/**
	 * Whether date archives are supported. If time archives are supported
	 * date archives are required to be enabled.
	 *
	 * @since 1.0.0
	 */
	public function hasDateArchives(): bool
	{
		return $this->date_archives || $this->hasTimeArchives();
	}

	/**
	 * Whether time archives are supported.
	 *
	 * @since 1.0.0
	 */
	public function hasTimeArchives(): bool
	{
		return $this->time_archives;
	}

	/**
	 * Whether routing is enabled.
	 *
	 * @since 1.0.0
	 */
	public function routing(): bool
	{
		return $this->routing;
	}

	/**
	 * Returns the type this content type collects.
	 *
	 * @since  1.0.0
	 */
	public function collect(): string|bool|null
	{
		return $this->collect;
	}

	/**
	 * Returns the type that terms of this type collects if a taxonomy.
	 *
	 * @since  1.0.0
	 */
	public function termCollect(): string|bool|null
	{
		return $this->term_collect;
	}

	/**
	 * Returns an array of Query arguments when the content type is used in
	 * a collection.
	 *
	 * @since 1.0.0
	 */
	public function collectionArgs(): array
	{
		return array_merge( [
			'type' => $this->collect() ?: $this->type()
		], $this->collection );
	}

	/**
	 * Returns an array of Query arguments when the terms of the content
	 * type is called as a collection. Only works for taxonomies.
	 *
	 * @since 1.0.0
	 */
	public function termCollectionArgs(): array
	{
		if ( ! $this->isTaxonomy() ) {
			return [];
		}

		$type = $this->termCollect() ?: $this->collect();

		return array_merge( [
			'type' => $type ?: $this->type()
		], $this->term_collection );
	}

	/**
	 * Returns an array of Query arguments when the content type is used in
	 * a feed.
	 *
	 * @since 1.0.0
	 */
	public function feedArgs(): array
	{
		return array_merge( [
			'type'    => $this->collect() ?: $this->type(),
			'order'   => 'desc',
			'orderby' => 'filename'
		], is_array( $this->feed ) ? $this->feed : [] );
	}

	/**
	 * Whether this type is a taxonomy.
	 *
	 * @since 1.0.0
	 */
	public function isTaxonomy(): bool
	{
		return $this->taxonomy;
	}

	/**
	 * Returns the content type routes as an array.
	 *
	 * @since 1.0.0
	 */
	public function routes(): array
	{
		// Return empty array if the content type doesn't support routes.
		if ( ! $this->routing() ) {
			return [];
		}

		// If routes are already stored, return them.
		if ( $this->routes ) {
			return $this->routes;
		}

		$type = $this->name();

		// Add paged type archive if not set as the homepage.
		if ( ! $this->isHomeAlias() ) {
			$this->routes[ $this->urlPath( 'collection.paged' ) ] = [
				'name'       => "{$type}.collection.paged",
				'controller' => Controllers\Collection::class
			];
		}

		// If the type supports a feed, add route.
		if ( $this->hasFeed() && ! $this->isHomeAlias() ) {
			$this->routes[ $this->urlPath( 'collection.feed' ) ] = [
				'name'       => "{$type}.collection.feed",
				'controller' => Controllers\CollectionFeed::class
			];
		}

		// If the type supports time-based archives, add routes.
		if ( $this->hasTimeArchives() ) {
			$archives = [
				"{$type}.collection.second.paged" => $this->urlPath( 'collection.second.paged' ),
				"{$type}.collection.second"       => $this->urlPath( 'collection.second'       ),
				"{$type}.collection.minute.paged" => $this->urlPath( 'collection.minute.paged' ),
				"{$type}.collection.minute"       => $this->urlPath( 'collection.minute'       ),
				"{$type}.collection.hour.paged"   => $this->urlPath( 'collection.hour.paged'   ),
				"{$type}.collection.hour"         => $this->urlPath( 'collection.hour'         )
			];

			foreach ( $archives as $name => $uri ) {
				$this->routes[$uri] = [
					'name'       => $name,
					'controller' => Controllers\CollectionArchiveDate::class
				];
			}
		}

		// If the type supports date-based archives, add routes.
		if ( $this->hasDateArchives() ) {
			$archives = [
				"{$type}.collection.day.paged"   => $this->urlPath( 'collection.day.paged'   ),
				"{$type}.collection.day"         => $this->urlPath( 'collection.day'         ),
				"{$type}.collection.month.paged" => $this->urlPath( 'collection.month.paged' ),
				"{$type}.collection.month"       => $this->urlPath( 'collection.month'       ),
				"{$type}.collection.year.paged"  => $this->urlPath( 'collection.year.paged'  ),
				"{$type}.collection.year"        => $this->urlPath( 'collection.year'        )
			];

			foreach ( $archives as $name => $uri ) {
				$this->routes[$uri] = [
					'name'       => $name,
					'controller' => Controllers\CollectionArchiveDate::class
				];
			}
		}

		// If this is a taxonomy, add paged term archive and single route.
		if ( $this->isTaxonomy() ) {
			$this->routes[ $this->urlPath( 'single.paged' ) ] = [
				'name'       => "{$type}.single.paged",
				'controller' => Controllers\CollectionTaxonomyTerm::class
			];

			$this->routes[ $this->urlPath( 'single' ) ] = [
				'name'       => "{$type}.single",
				'controller' => Controllers\CollectionTaxonomyTerm::class
			];
		}

		// Add single route if not a taxonomy.
		if ( ! $this->isTaxonomy() ) {
			$this->routes[ $this->urlPath( 'single' ) ] = [
				'name'       => "{$type}.single",
				'controller' => Controllers\Single::class
			];
		}

		// Add type archive route if not set as the homepage.
		if ( ! $this->isHomeAlias() ) {
			$this->routes[ $this->urlPath( 'collection' ) ] = [
				'name'       => "{$type}.collection",
				'controller' => Controllers\Collection::class
			];
		}

		// Return the built routes.
		return $this->routes;
	}

	/**
	 * When attempting to use the object as a string, return the result
	 * of the `name()` method.
	 *
	 * @since 1.0.0
	 */
	public function __toString(): string
	{
		return $this->name();
	}
}
