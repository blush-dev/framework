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

use Blush\{App, Config};
use Blush\Tools\{Collection, Str};

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
	 * Returns an environment variable if it is set or `null`.
	 *
	 * @since  1.0.0
	 */
	function env( string $var, ?string $default = null ): ?string
	{
		return $_ENV[ $var ] ?? $default;
	}
}

if ( ! function_exists( 'config' ) ) {
	/**
	 * Returns a value from the configuration instance using either dot
	 * (e.g., `app.uri`) or slash (e.g., `app/uri`) notation.
	 *
	 * @since  1.0.0
	 * @param  string  $deprecated  Setting key. Use dot notation instead.
	 * @return mixed
	 */
	function config( string $name, string $deprecated = '' )
	{
		if ( $deprecated && ! Str::contains( $name, '.' ) ) {
			$name = "{$name}.{$deprecated}";
		}

		return Config::get( $name );
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
		return app()->path( '', $append );
	}
}

if ( ! function_exists( 'app_path' ) ) {
	/**
	 * Returns app path with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	function app_path( string $append = '' ) : string
	{
		return app()->app_path( $append );
	}
}

if ( ! function_exists( 'config_path' ) ) {
	/**
	 * Returns public path with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	function config_path( string $append = '' ) : string
	{
		return app()->config_path( $append );
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
		return app()->public_path( $append );
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
		return app()->view_path( $append );
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
		return app()->resource_path( $append );
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
		return app()->storage_path( $append );
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
		return app()->cache_path( $append );
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
		return app()->user_path( $append );
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
		return app()->content_path( $append );
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
		return app()->media_path( $append );
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
		return app()->uri( '', $append );
	}
}

if ( ! function_exists( 'app_uri' ) ) {
	/**
	 * Returns config URI with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	function app_uri( string $append = '' ) : string
	{
		return app()->app_uri( $append );
	}
}

if ( ! function_exists( 'config_uri' ) ) {
	/**
	 * Returns config URI with optional appended path/file.
	 *
	 * @since 1.0.0
	 */
	function config_uri( string $append = '' ) : string
	{
		return app()->config_uri( $append );
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
		return app()->public_uri( $append );
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
		return app()->view_uri( $append );
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
		return app()->resource_uri( $append );
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
		return app()->storage_uri( $append );
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
		return app()->cache_uri( $append );
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
		return app()->user_uri( $append );
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
		return app()->content_uri( $append );
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
		return app()->media_uri( $append );
	}
}

if ( ! function_exists( 'asset' ) ) {
	/**
	 * Returns an asset URI with an ID query var attached to it based on the
	 * file's last modified time. Used for cache busting. The `$path` param
	 * must be a filename relative to the public path.
	 *
	 * @since 1.0.0
	 */
	function asset( string $path ): string
	{
		$asset_uri = public_uri( $path );
		$modified = filemtime( public_path( $path ) );

		return false !== $modified ? "{$asset_uri}?id={$modified}" : $asset_uri;
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
	function sanitize_slug( string $str, string $sep = '-' ) : string
	{
		return Str::slug( $str, $sep );
	}
}
