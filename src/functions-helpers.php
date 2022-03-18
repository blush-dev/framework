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
use Blush\Tools\{Collection, Config, Str};

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
