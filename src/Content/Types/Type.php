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

use Blush\{Config, Url};
use Blush\Controllers;
use Blush\Tools\Str;

class Type
{
	/**
	 * Name of the content type.
	 *
	 * @since 1.0.0
	 */
	protected string $type = 'page';

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
	 * The content type that this content type collects in archives.
	 *
	 * @since 1.0.0
	 * @var   string|false|null
	 */
	protected $collect = null;

	/**
	 * If the content type is a taxonomy, the content type that the
	 * taxonomy terms collect.
	 *
	 * @since 1.0.0
	 * @var   string|false|null
	 */
	protected $term_collect = null;

	/**
	 * Array of Query args when type is called as a collection.
	 *
	 * @since 1.0.0
	 */
	protected $collection = [];

	/**
	 * Array of Query args when a taxonomy term is called as a collection.
	 *
	 * @since 1.0.0
	 */
	protected $term_collection = [];

	/**
	 * Whether to generate date-based archives for the content type.
	 *
	 * @since 1.0.0
	 */
	protected bool $date_archives = false;

	/**
	 * Stores the URI path for the content type.
	 *
	 * @since 1.0.0
	 */
	protected string $uri = '';

	/**
	 * Stores the single entry URI path for the content type.
	 *
	 * @since 1.0.0
	 */
	protected string $uri_single = '';

	/**
	 * Sets up the object state.
	 *
	 * @since 1.0.0
	 */
	public function __construct( string $type, array $options = [] )
	{
		foreach ( array_keys( get_object_vars( $this ) ) as $key ) {
			if ( isset( $options[ $key ] ) ) {
				$this->$key = $options[ $key ];
			}
		}

		// If the content type doesn't collect another, it should
		// collect itself.
		if ( is_null( $this->collect ) && false !== $this->collect ) {
			$this->collect = $type;
		}

		// Parse routes passed in via `[ $uri => $controller ]` so that
		// they are stored as `$uri => $args`.
		if ( $this->routes ) {
			$_routes = [];
			foreach ( $this->routes as $route => $args ) {
				$_routes[ $route ] = is_string( $args )
					? [ 'controller' => $args ]
					: $args;
			}
			$this->routes = $_routes;
		}

		// Set the type name.
		$this->type = $type;

		// If there is no URI, set it to the path.
		if ( ! $this->uri ) {
			$this->uri = $this->path;
		}

		// If there is no single URI, append the `{name}` param to the URI.
		if ( ! $this->uri_single ) {
			$this->uri_single = Str::appendUri( $this->uri, '{name}' );
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
	 * Returns the content type path.
	 *
	 * @since 1.0.0
	 */
	public function path(): string
	{
		return $this->path;
	}

	/**
	 * Returns the content type URL path.
	 *
	 * @since 1.0.0
	 */
	public function urlPath(): string
	{
		return $this->uri;
	}

	/**
	 * Returns the content type URL path for single entries.
	 *
	 * @since 1.0.0
	 */
	public function singleUrlPath(): string
	{
		return $this->uri_single;
	}

	/**
	 * Returns the content type URL.
	 *
	 * @since 1.0.0
	 */
	public function url(): string
	{
		$url = Url::route( $this->name() . '.collection' );

		return $url ?: Url::route( $this->urlPath() );
	}

	/**
	 * Returns the content type URL for single entries.
	 *
	 * @since 1.0.0
	 */
	public function singleUrl( array $params = [] ): string
	{
		$url = Url::route( $this->name() . '.single', $params );

		return $url ?: Url::route( $this->singleUrlPath() );
	}

	/**
	 * Returns the content type URL for year archives.
	 *
	 * @since 1.0.0
	 */
	public function yearUrl( string $year ): string
	{
		return Url::route( $this->name() . '.collection.year', [
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
		return Url::route( $this->name() . '.collection.month', [
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
		return Url::route( $this->name() . '.collection.day', [
			'year'  => $year,
			'month' => $month,
			'day'   => $day
		] );
	}

	/**
	 * Whether date archives are supported.
	 *
	 * @since 1.0.0
	 */
	public function hasDateArchives(): bool
	{
		return $this->date_archives;
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

		$type  = $this->name();
		$path  = $this->urlPath();
		$alias = Config::get( 'app.home_alias' );

		// Add paged type archive if not set as the homepage.
		if ( $alias !== $this->type() ) {
			$this->routes[ $path . '/page/{page}' ] = [
				'name'       => "{$type}.collection.paged",
				'controller' => Controllers\Collection::class
			];
		}

		// If the type supports date-based archives, add routes.
		if ( $this->hasDateArchives() ) {
			$archives = [
				"{$type}.collection.day.paged"   => '{year}/{month}/{day}/page/{page}',
				"{$type}.collection.day"         => '{year}/{month}/{day}',
				"{$type}.collection.month.paged" => '{year}/{month}/page/{page}',
				"{$type}.collection.month"       => '{year}/{month}',
				"{$type}.collection.year.paged"  => '{year}/page/{page}',
				"{$type}.collection.year"        => '{year}'
			];

			foreach ( $archives as $name => $uri ) {
				$this->routes[ "{$path}/{$uri}" ] = [
					'name'       => $name,
					'controller' => Controllers\CollectionArchiveDate::class
				];
			}
		}

		// If this is a taxonomy, add paged term archive and single route.
		if ( $this->isTaxonomy() ) {
			$this->routes[ $path . '/{name}/page/{page}' ] = [
				'name'       => "{$type}.single.paged",
				'controller' => Controllers\CollectionTaxonomyTerm::class
			];

			$this->routes[ $this->singleUrlPath() ] = [
				'name'       => "{$type}.single",
				'controller' => Controllers\CollectionTaxonomyTerm::class
			];
		}

		// Add single route if not a taxonomy.
		if ( ! $this->isTaxonomy() ) {
			$this->routes[ $this->singleUrlPath() ] = [
				'name'       => "{$type}.single",
				'controller' => Controllers\Single::class
			];
		}

		// Add type archive route if not set as the homepage.
		if ( $alias !== $type ) {
			$this->routes[ $path ] = [
				'name'       => "{$type}.collection",
				'controller' => Controllers\Collection::class
			];
		}

		// Return the built routes.
		return $this->routes;
	}

	/**
	 * Returns the type this content type collects.
	 *
	 * @since  1.0.0
	 * @return string|false|null
	 */
	public function collect()
	{
		return $this->collect;
	}

	/**
	 * Returns the type that terms of this type collects if a taxonomy.
	 *
	 * @since  1.0.0
	 * @return string|false|null
	 */
	public function termCollect()
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
	 * Whether this type is a taxonomy.
	 *
	 * @since 1.0.0
	 */
	public function isTaxonomy(): bool
	{
		return $this->taxonomy;
	}
}
