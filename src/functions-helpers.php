<?php
/**
 * Helper functions.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

use Blush\App;
use Blush\Cache;
use Blush\Tools\{Collection, Config, Str};
use Symfony\Component\Yaml\Yaml;

if ( ! function_exists( 'app' ) ) {
	/**
	 * Returns an instance of the application or a binding.
	 *
	 * @since  1.0.0
	 * @return mixed
	 */
	function app( $abstract = '', array $params = [] )
	{
		return App::resolve( $abstract ?: 'app', $params );
	}
}

if ( ! function_exists( 'env' ) ) {
	/**
	 * Returns an environment variable if it is set or `false`.
	 *
	 * @since  1.0.0
	 * @return string
	 */
	function env( string $var, ?string $default = null ): ?string
	{
		return $_ENV[ $var ] ?? $default;
	}
}

if ( ! function_exists( 'config' ) ) {
	/**
	 * Returns and instances of a config object or a setting.
	 *
	 * @since  1.0.0
	 * @return mixed
	 */
	function config( string $name, string $setting = '' )
	{
		$config = Str::beforeFirst( $name, '.' );
		$option = Str::afterFirst( $name, '.' );

		if ( $config === $option ) {
			$option = false;
		}

		if ( $option && ! $setting ) {
			$setting = $option;
		}

		$config = app( "config.{$config}" );

		if ( $setting ) {
			return $config[ $setting ] ?? null;
		}

		return $config;
	}
}

if ( ! function_exists( 'path' ) ) {
	/**
	 * Returns app path with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	function path( string $append = '' ) : string
	{
		return Str::appendPath( app( 'path' ), $append );
	}
}

if ( ! function_exists( 'uri' ) ) {
	/**
	 * Returns app URI with optionsal appended path.
	 *
	 * @since 1.0.0
	 */
	function uri( string $append = '' ) : string
	{
		return Str::appendUri( app( 'uri' ), $append );
	}
}

if ( ! function_exists( 'public_path' ) ) {
	/**
	 * Returns public path with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	function public_path( string $append = '' ) : string
	{
		return Str::appendPath( app( 'path.public' ), $append );
	}
}

if ( ! function_exists( 'view_path' ) ) {
	/**
	 * Returns view path with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	function view_path( string $append = '' ) : string
	{
		return Str::appendPath( app( 'path.view' ), $append );
	}
}

if ( ! function_exists( 'resource_path' ) ) {
	/**
	 * Returns resource path with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	function resource_path( string $append = '' ) : string
	{
		return Str::appendPath( app( 'path.resource' ), $append );
	}
}

if ( ! function_exists( 'storage_path' ) ) {
	/**
	 * Returns storage path with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	function storage_path( string $append = '' ) : string
	{
		return Str::appendPath( app( 'path.storage' ), $append );
	}
}

if ( ! function_exists( 'cache_path' ) ) {
	/**
	 * Returns cache path with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	function cache_path( string $append = '' ) : string
	{
		return Str::appendPath( app( 'path.cache' ), $append );
	}
}

if ( ! function_exists( 'user_path' ) ) {
	/**
	 * Returns user path with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	function user_path( string $append = '' ) : string
	{
		return Str::appendPath( app( 'path.user' ), $append );
	}
}

if ( ! function_exists( 'content_path' ) ) {
	/**
	 * Returns content path with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	function content_path( string $append = '' ) : string
	{
		return Str::appendPath( app( 'path.content' ), $append );
	}
}

if ( ! function_exists( 'media_path' ) ) {
	/**
	 * Returns media path with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	function media_path( string $append = '' ) : string
	{
		return Str::appendPath( app( 'path.media' ), $append );
	}
}

if ( ! function_exists( 'public_uri' ) ) {
	/**
	 * Returns public URI with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	function public_uri( string $append = '' ) : string
	{
		return Str::appendUri( app( 'uri.public' ), $append );
	}
}

if ( ! function_exists( 'view_uri' ) ) {
	/**
	 * Returns view URI with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	function view_uri( string $append = '' ) : string
	{
		return Str::appendUri( app( 'uri.view' ), $append );
	}
}

if ( ! function_exists( 'resource_uri' ) ) {
	/**
	 * Returns resource URI with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	function resource_uri( string $append = '' ) : string
	{
		return Str::appendUri( app( 'uri.resource' ), $append );
	}
}

if ( ! function_exists( 'storage_uri' ) ) {
	/**
	 * Returns storage URI with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	function storage_uri( string $append = '' ) : string
	{
		return Str::appendUri( app( 'uri.storage' ), $append );
	}
}

if ( ! function_exists( 'cache_uri' ) ) {
	/**
	 * Returns cache URI with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	function cache_uri( string $append = '' ) : string
	{
		return Str::appendUri( app( 'uri.cache' ), $append );
	}
}

if ( ! function_exists( 'user_uri' ) ) {
	/**
	 * Returns public URI with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	function user_uri( string $append = '' ) : string
	{
		return Str::appendUri( app( 'uri.user' ), $append );
	}
}

if ( ! function_exists( 'content_uri' ) ) {
	/**
	 * Returns content URI with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	function content_uri( string $append = '' ) : string
	{
		return Str::appendUri( app( 'uri.content' ), $append );
	}
}

if ( ! function_exists( 'media_uri' ) ) {
	/**
	 * Returns media URI with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	function media_uri( string $append = '' ) : string
	{
		return Str::appendUri( app( 'uri.media' ), $append );
	}
}

if ( ! function_exists( 'e' ) ) {
	/**
	 * Convenient wrapper around `htmlspecialchars()` for escaping strings
	 * before outputting HTML.
	 *
	 * @since 1.0.0
	 */
	function e( string $value, bool $double_encode = true ) : string
	{
		return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8', $double_encode );
	}
}

if ( ! function_exists( 'escape_tag' ) ) {
	/**
	 * Escapes an HTML tag name for use in HTML. Note that this does not
	 * validate the tag, only makes it safe for output.
	 *
	 * @since 1.0.0
	 */
	function escape_tag( string $tag ) : string
	{
		return strtolower( preg_replace( '/[^a-zA-Z0-9_:]/', '', $tag ) );
	}
}

if ( ! function_exists( 'sanitize_slug' ) ) {
	/**
	 * Sanitizes a string meant to be used as a slug.
	 *
	 * @since 1.0.0
	 */
	function sanitize_slug( string $str, string $sep = '' ) : string
	{
		$dividers = $sep === '-' ? '_' : '-';

		$str = preg_replace( '/[' . preg_quote( $dividers ) . ']+/u', $sep, $str );

		$str = preg_replace( '/[^' . preg_quote( $sep ) . '\pL\pN\s]+/u', $sep, $str );

		$str = preg_replace( '/[' . preg_quote( $sep ) . '\s]+/u', $sep, $str );

		return trim( strtolower( $str ), $sep );
	}
}

if ( ! function_exists( 'sanitize_with_dashes' ) ) {
	/**
	 * Sanitizes a string meant to be used as a slug.
	 *
	 * @since 1.0.0
	 */
	function sanitize_with_dashes( string $str ) : string
	{
		return sanitize_slug( $str, '-' );
	}
}

if ( ! function_exists( 'posts_per_page' ) ) {
	/**
	 * DO NOT USE. WILL BE REMOVED IN FAVOR OF SETTING/CONFIG.
	 *
	 * @since 1.0.0
	 */
	function posts_per_page() {
		return 10;
	}
}

if ( ! function_exists( 'caches' ) ) {
	/**
	 * Returns the cache collection.
	 *
	 * @since 1.0.0
	 */
	function caches() : Collection
	{
		return App::resolve( 'caches' );
	}
}

if ( ! function_exists( 'cache' ) ) {
	/**
	 * Returns a cache or false.
	 *
	 * @since 1.0.0
	 * @return  Cache\Cache|false
	 */
	function cache( string $name )
	{
		return caches()->has( $name ) ? caches()->get( $name ) : false;
	}
}

if ( ! function_exists( 'cache_get_make' ) ) {
	/**
	 * Gets a cache. If it does not exist, it adds the cache to the
	 * collection and creates the cache storage.
	 *
	 * @since  1.0.0
	 * @return mixed
	 */
	function cache_get_make( string $name, string $type = 'collection' )
	{
		$cache = caches();

		if ( $cache->has( $name ) ) {
			return $cache->get( $name )->get();
		}

		cache_add( $name, $type );

		$_cache = $cache->get( $name );
		$_cache->make();

		return $_cache->get();
	}
}

if ( ! function_exists( 'cache_get_add' ) ) {
	/**
	 * Gets cache. If it does not exist, adds it to the collection.
	 *
	 * @since  1.0.0
	 * @return mixed
	 */
	function cache_get_add( string $name, string $type = 'collection' )
	{
		$cache = caches();

		if ( $cache->has( $name ) ) {
			return $cache->get( $name )->get();
		}

		cache_add( $name, $type );

		$_cache = $cache->get( $name );

		return $_cache->get();
	}
}

if ( ! function_exists( 'cache_get' ) ) {
	/**
	 * Returns a cache or false.
	 *
	 * @since  1.0.0
	 * @return mixed
	 */
	function cache_get( string $name )
	{
		$cache = caches();
		return $cache->has( $name ) ? $cache->get( $name )->get() : false;
	}
}

if ( ! function_exists( 'cache_set' ) ) {
	/**
	 * Adds data to a cache.
	 *
	 * @since  1.0.0
	 * @param  mixed  $data
	 */
	function cache_set( string $name, $data, string $type = 'collect' ) : void
	{
		$cache = caches();

		if ( ! $cache->has( $name ) ) {
			cache_add( $name, $type );
		}

		$cache->get( $name )->set( $data );
	}
}

if ( ! function_exists( 'cache_add' ) ) {
	/**
	 * Adds a new cache.
	 *
	 * @since 1.0.0
	 */
	function cache_add( string $name, string $type = 'collection' ) : void
	{
		$cache = caches();

		$map = [
			'collection' => Cache\Collection::class,
			'html'       => Cache\Html::class,
			'json'       => Cache\Json::class,
			'rapid'      => Cache\Rapid::class
		];

		if ( isset( $map[ $type ] ) ) {
			$callback = $map[ $type ];
			$cache->add( $name, new $callback( $name ) );
		}
	}
}

if ( ! function_exists( 'cache_delete' ) ) {
	/**
	 * Deletes a cache.
	 *
	 * @since 1.0.0
	 */
	function cache_delete( string $name ) {
		$cache = caches();

		if ( $cache->has( $name ) ) {
			$cache->get( $name )->delete();
		}
	}
}
