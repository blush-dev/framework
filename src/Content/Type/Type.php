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

namespace Blush\Content\Type;

use Blush\Contracts\Content\ContentType;
use Blush\Core\Proxies\{App, Config, Url};
use Blush\Controllers;
use Blush\Tools\Str;

class Type implements ContentType
{
	/**
	 * Content type path.
	 *
	 * @since 1.0.0
	 */
	protected string $path = '';

	/**
	 * Whether the type is public.
	 *
	 * @since 1.0.0
	 */
	protected bool $public = true;

	/**
	 * Array of content type routes.
	 *
	 * @since 1.0.0
	 */
	protected array $routes = [];

	/**
	 * Routing group prefix.
	 *
	 * @since 1.0.0
	 */
	protected string $routing_prefix = '';

	/**
	 * Routing paths with named keys and a URI string.
	 *
	 * @since 1.0.0
	 */
	protected array $routing_paths = [];

	/**
	 * Whether routing is enabled for this content type.
	 *
	 * @since 1.0.0
	 */
	protected bool $has_routing = true;

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
	protected ?array $feed = null;

	/**
	 * Whether to generate a sitemap for the content type. Sitemaps must
	 * also be enabled globally.
	 *
	 * @since 1.0.0
	 */
	protected bool $sitemap = true;

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
	 * Sets up the object state.
	 *
	 * @since 1.0.0
	 */
	public function __construct( protected string $name, array $options = [] )
	{
		// Set up the object properties based on parameters.
		$this->path            = $options['path']            ?? $name;
		$this->public          = $options['public']          ?? true;
		$this->collection      = $options['collection']      ?? [];
		$this->routes          = $options['routes']          ?? [];
		$this->date_archives   = $options['date_archives']   ?? false;
		$this->time_archives   = $options['time_archives']   ?? false;
		$this->taxonomy        = $options['taxonomy']        ?? false;
		$this->term_collect    = $options['term_collect']    ?? null;
		$this->term_collection = $options['term_collection'] ?? [];
		$this->sitemap         = $options['sitemap']         ?? true;
		$this->has_routing     = isset( $options['routing'] ) && false !== $options['routing'];

		// Unless the `collect` option is explicitly set to `false`,
		// it should at least collect itself.
		if ( ! isset( $options['collect'] ) || false !== $options['collect'] ) {
			$this->collect = $options['collect'] ?? $name;
		}

		// Build the feed options.
		$feed = [
			'taxonomy'   => null,
			'collection' => []
		];

		if ( isset( $options['feed'] ) && true === $options['feed'] ) {
			$this->feed = $feed;
		} elseif ( isset( $options['feed'] ) && is_array( $options['feed'] ) ) {
			$this->feed = array_merge( $feed, $options['feed'] );
		}

		// Sets up routing prefix and paths.
		if ( $this->hasRouting() ) {
			$this->routing_prefix = $options['routing']['prefix'] ?? $this->path;

			$this->routing_paths = array_merge( [
				'collection'              => '' ,
				'single'                  => '{name}',
				'single.paged'            => "{name}/page/{page}",
				'collection.feed.atom'    => "feed/atom",
				'collection.feed'         => "feed",
				'collection.paged'        => "page/{page}",
				'collection.second.paged' => "{year}/{month}/{day}/{hour}/{minute}/{second}/page/{page}",
				'collection.second'       => "{year}/{month}/{day}/{hour}/{minute}/{second}",
				'collection.minute.paged' => "{year}/{month}/{day}/{hour}/{minute}/page/{page}",
				'collection.minute'       => "{year}/{month}/{day}/{hour}/{minute}",
				'collection.hour.paged'   => "{year}/{month}/{day}/{hour}/page/{page}",
				'collection.hour'         => "{year}/{month}/{day}/{hour}",
				'collection.day.paged'    => "{year}/{month}/{day}/page/{page}",
				'collection.day'          => "{year}/{month}/{day}",
				'collection.month.paged'  => "{year}/{month}/page/{page}",
				'collection.month'        => "{year}/{month}",
				'collection.year.paged'   => "{year}/page/{page}",
				'collection.year'         => "{year}"
			], $options['routing']['paths'] ?? [] );
		}
	}

	/**
	 * Returns the content type name.
	 *
	 * @since 1.0.0
	 */
	public function name(): string
	{
		return $this->name;
	}

	/**
	 * Returns the content type name (alias for `name()`).
	 *
	 * @since 1.0.0
	 * @deprecated 1.0.0
	 */
	public function type(): string
	{
		return $this->name();
	}

	/**
	 * Conditional check if the type is the homepage alias.
	 *
	 * @since 1.0.0
	 */
	public function isHomeAlias(): bool
	{
		return $this->name() === Config::get( 'app.home_alias' );
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
	 * Conditional check for whether the content type is public.
	 *
	 * @since 1.0.0
	 */
	public function isPublic(): bool
	{
		return $this->public;
	}

	/**
	 * Returns a keyed URL path.
	 *
	 * @since 1.0.0
	 */
	public function urlPath( string $key = '' ): string
	{
		if ( ! $key || 'collection' === $key ) {
			$path = $this->routePath( 'collection' );
			return $path ?: $this->routingPrefix();
		}

		return $this->routePath( $key );
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
		       : Url::route( $this->routeName( 'collection' ) );
	}

	/**
	 * Returns the content type URL for single entries.
	 *
	 * @since 1.0.0
	 */
	public function singleUrl( array $params = [] ): string
	{
		return Url::route( $this->routeName( 'single' ), $params );
	}

	/**
	 * Returns the content type feed URL.
	 *
	 * @since 1.0.0
	 */
	public function feedUrl(): string
	{
		return $this->rssFeedUrl();
	}

	/**
	 * Returns the content type RSS feed URL.
	 *
	 * @since 1.0.0
	 */
	public function rssFeedUrl(): string
	{
		return $this->isHomeAlias()
		       ? Url::route( 'feed' )
		       : Url::route( $this->routeName( 'collection.feed' ) );
	}

	/**
	 * Returns the content type atom feed URL.
	 *
	 * @since 1.0.0
	 */
	public function atomFeedUrl(): string
	{
		return $this->isHomeAlias()
		       ? Url::route( 'feed/atom' )
		       : Url::route( $this->routeName( 'collection.feed.atom' ) );
	}

	/**
	 * Returns the sitemap URL.
	 *
	 * @since 1.0.0
	 */
	public function sitemapUrl(): string
	{
		return Url::route( 'sitemap/{type}', [ 'type' => $this->name() ] );
	}

	/**
	 * Returns the content type URL for year archives.
	 *
	 * @since 1.0.0
	 */
	public function yearUrl( string $year ): string
	{
		return Url::route( $this->routeName( 'collection.year' ), [
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
		return Url::route( $this->routeName( 'collection.month' ), [
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
		return Url::route( $this->routeName( 'collection.day' ), [
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
		return is_array( $this->feed );
	}

	/**
	 * Returns the taxonomy used with entries of this type in feeds.  Each
	 * term is of the taxonomy is output as a `<category>`.
	 *
	 * @since 1.0.0
	 */
	public function feedTaxonomy(): ContentType|null
	{
		if ( ! $taxonomy = $this->feed['taxonomy'] ) {
			return null;
		}

		if ( App::get( 'content.types' )->has( $taxonomy ) ) {
			return App::get( 'content.types' )->get( $taxonomy );
		}

		return null;
	}

	/**
	 * Whether the content type has a sitemap.
	 *
	 * @since 1.0.0
	 */
	public function hasSitemap(): bool
	{
		return Config::get( 'sitemap' ) && $this->sitemap;
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
	public function hasRouting(): bool
	{
		return $this->has_routing;
	}

	/**
	 * Returns the routing group prefix.
	 *
	 * @since 1.0.0
	 */
	public function routingPrefix(): string
	{
		return $this->routing_prefix;
	}

	/**
	 * Returns a route name.
	 *
	 * @since 1.0.0
	 */
	protected function routeName( string $key ): string
	{
		$name = $this->type();

		return "{$name}.{$key}";
	}

	/**
	 * Returns a route path.
	 *
	 * @since 1.0.0
	 */
	protected function routePath( string $key = '' ): string
	{
		return $this->routing_paths[ $key ] ?? '';
	}

	/**
	 * Returns the routing paths.
	 *
	 * @since 1.0.0
	 */
	public function routingPaths(): array
	{
		return $this->routing_paths;
	}

	/**
	 * Whether routing is enabled.
	 *
	 * @since 1.0.0
	 * @deprecated 1.0.0 No longer used.
	 */
	public function routing(): array|bool
	{
		return $this->hasRouting();
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
			'type' => $this->collect() ?: $this->name()
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

		$name = $this->termCollect() ?: $this->collect();

		return array_merge( [
			'type' => $name ?: $this->name()
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
		if ( ! is_array( $this->feed ) ) {
			return [];
		}

		return array_merge( [
			'type'    => $this->collect() ?: $this->name(),
			'order'   => 'desc',
			'orderby' => 'filename'
		], $this->feed['collection'] ?? [] );
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
		if ( ! $this->hasRouting() ) {
			return [];
		}

		// If routes are already stored, return them.
		if ( $this->routes ) {
			return $this->routes;
		}

		$name = $this->name();

		// Add paged type archive if not set as the homepage.
		if ( ! $this->isHomeAlias() ) {
			$this->routes[ $this->routePath( 'collection.paged' ) ] = [
				'name'       => "{$name}.collection.paged",
				'controller' => Controllers\Collection::class
			];
		}

		// If the type supports a feed, add feed routes.
		if ( $this->hasFeed() && ! $this->isHomeAlias() ) {
			$this->routes[ $this->routePath( 'collection.feed.atom' ) ] = [
				'name'       => "{$name}.collection.feed.atom",
				'controller' => Controllers\CollectionFeedAtom::class
			];

			$this->routes[ $this->routePath( 'collection.feed' ) ] = [
				'name'       => "{$name}.collection.feed",
				'controller' => Controllers\CollectionFeed::class
			];
		}

		// If the type supports time-based archives, add routes.
		if ( $this->hasTimeArchives() ) {
			$archives = [
				"{$name}.collection.second.paged" => $this->routePath( 'collection.second.paged' ),
				"{$name}.collection.second"       => $this->routePath( 'collection.second'       ),
				"{$name}.collection.minute.paged" => $this->routePath( 'collection.minute.paged' ),
				"{$name}.collection.minute"       => $this->routePath( 'collection.minute'       ),
				"{$name}.collection.hour.paged"   => $this->routePath( 'collection.hour.paged'   ),
				"{$name}.collection.hour"         => $this->routePath( 'collection.hour'         )
			];

			foreach ( $archives as $time => $uri ) {
				$this->routes[$uri] = [
					'name'       => $time,
					'controller' => Controllers\CollectionArchiveDate::class
				];
			}
		}

		// If the type supports date-based archives, add routes.
		if ( $this->hasDateArchives() ) {
			$archives = [
				"{$name}.collection.day.paged"   => $this->routePath( 'collection.day.paged'   ),
				"{$name}.collection.day"         => $this->routePath( 'collection.day'         ),
				"{$name}.collection.month.paged" => $this->routePath( 'collection.month.paged' ),
				"{$name}.collection.month"       => $this->routePath( 'collection.month'       ),
				"{$name}.collection.year.paged"  => $this->routePath( 'collection.year.paged'  ),
				"{$name}.collection.year"        => $this->routePath( 'collection.year'        )
			];

			foreach ( $archives as $date => $uri ) {
				$this->routes[$uri] = [
					'name'       => $date,
					'controller' => Controllers\CollectionArchiveDate::class
				];
			}
		}

		// If this is a taxonomy, add paged term archive and single route.
		if ( $this->isTaxonomy() ) {
			$this->routes[ $this->routePath( 'single.paged' ) ] = [
				'name'       => "{$name}.single.paged",
				'controller' => Controllers\CollectionTaxonomyTerm::class
			];

			$this->routes[ $this->routePath( 'single' ) ] = [
				'name'       => "{$name}.single",
				'controller' => Controllers\CollectionTaxonomyTerm::class
			];
		}

		// Add single route if not a taxonomy.
		if ( ! $this->isTaxonomy() ) {
			$this->routes[ $this->routePath( 'single' ) ] = [
				'name'       => "{$name}.single",
				'controller' => Controllers\Single::class
			];
		}

		// Add type archive route if not set as the homepage.
		if ( ! $this->isHomeAlias() ) {
			$this->routes[ $this->routePath( 'collection' ) ] = [
				'name'       => "{$name}.collection",
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
