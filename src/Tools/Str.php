<?php
/**
 * String class.
 *
 * This file houses a collection of static methods for working with strings.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Tools;

class Str
{
	/**
	 * Returns part of a string after the first occurrence.
	 *
	 * @since 1.0.0
	 */
	public static function afterFirst( string $str, string $search ): string
	{
		if ( '' === $str ) {
			return $str;
		}

		$parts = array_reverse( explode( $search, $str, 2 ) );

		return array_shift( $parts );
	}

	/**
	 * Returns part of a string after the last occurrence.
	 *
	 * @since 1.0.0
	 */
	public static function afterLast( string $str, string $search ): string
	{
		if ( '' === $search ) {
			return $str;
		}

		$pos = strrpos( $str, $search );

		if ( false === $pos ) {
			return $str;
		}

		return substr( $str, $pos + strlen( $search ) );
	}

	/**
	 * Trims slashes and appends a path to a path.
	 *
	 * @since 1.0.0
	 */
	public static function appendPath( string $path, string $append = '' ): string
	{
		$path   = rtrim( $path, '/\\' );
		$append = ltrim( $append, '/\\' );
		return $append ? "{$path}/{$append}" : $path;
	}

	/**
	 * Trims slashes and appends a path to a URI.
	 *
	 * @since 1.0.0
	 */
	public static function appendUri( string $uri, string $append = '' ): string
	{
		return static::appendPath( $uri, $append );
	}

	/**
	 * Returns part of a string before the first occurrence.
	 *
	 * @since 1.0.0
	 */
	public static function beforeFirst( string $str, string $search ): string
	{
		if ( '' === $str ) {
			return $str;
		}

		$before = strstr( $str, $search, true );

		return false === $before ? $str : $before;
	}

	/**
	 * Returns part of a string before the last occurrence.
	 *
	 * @since 1.0.0
	 */
	public static function beforeLast( string $str, string $search ): string
	{
		if ( '' === $search ) {
			return $str;
		}

		$pos = mb_strrpos( $str, $search );

		if ( false === $pos ) {
			return $str;
		}

		return static::substr( $str, 0, $pos );
	}

	/**
	 * Returns a string between two strings.
	 *
	 * @since 1.0.0
	 */
	public static function between( string $str, string $from, string $to ): string
	{
		if ( ! $str || ! $from || ! $to ) {
			return $str;
		}

		return static::beforeFirst( static::afterFirst( $str, $from ), $to );
	}

	/**
	 * Checks if a string contains another string.
	 *
	 * @since 1.0.0
	 */
	public static function contains( string $haystack, string $needle ): bool
	{
		// PHP 8.
		if ( function_exists( 'str_contains' ) ) {
			return str_contains( $haystack, $needle );
		}

		return false !== mb_strpos( $haystack, $needle );
	}

	/**
	 * Checks if a string contains all of the provided array of strings.
	 *
	 * @since 1.0.0
	 */
	public static function containsAll( string $haystack, array $needles ): bool
	{
		foreach ( $needles as $needle ) {
			if ( false === static::contains( $haystack, $needle ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Checks if a string contains any of the provided array of strings.
	 *
	 * @since 1.0.0
	 */
	public static function containsAny( string $haystack, array $needles ): bool
	{
		foreach ( $needles as $needle ) {
			if ( true === static::contains( $haystack, $needle ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Normalizes the filesystem path to use `/` instead of `\` for the
	 * directory separator.
	 *
	 * @since 1.0.0
	 */
	public static function normalizePath( string $path ): string
	{
		$path = str_replace( '\\', '/', $path );
		$path = preg_replace( '|(?<=.)/+|', '/', $path );

		if ( ':' === static::substr( $path, 1, 1 ) ) {
			$path = ucfirst( $path );
		}

		return $path;
	}

	/**
	 * Returns the singular or plural version of a string based on the count.
	 *
	 * @todo  Implement translation system that handles this based on locale.
	 * @since 1.0.0
	 */
	public static function nText( string $singular, string $plural, int $count ): string
	{
		return 1 === $count ? $singular : $plural;
	}

	/**
	 * Replaces the last occurrence of a string.
	 *
	 * @since 1.0.0
	 */
	public static function replaceLast( string $search, string $replace, string $str ): string
	{
		if ( '' === $search ) {
			return $str;
		}

		$pos = strpos( $str, $search );

		if ( false !== $pos ) {
			return substr_replace( $str, $replace, $pos, strlen( $search ) );
		}

		return $str;
	}

	/**
	 * Adds a slash after a string.
	 *
	 * @since 1.0.0
	 */
	public static function slashAfter( string $str ): string
	{
		return rtrim( $str, '/\\' ) . '/';
	}

	/**
	 * Adds a slash before a string.
	 *
	 * @since 1.0.0
	 */
	public static function slashBefore( string $str ): string
	{
		return '/' . ltrim( $str, '/\\' );
	}

	/**
	 * Trims slashes from both sides of string.
	 *
	 * @since 1.0.0
	 */
	public static function slashTrim( string $str ): string
	{
		return trim( $str, '/\\' );
	}

	/**
	 * Sanitizes a string meant to be used as a slug.
	 *
	 * @since 1.0.0
	 */
	public static function slug( string $str, string $sep = '-' ): string
	{
		$dividers = $sep === '-' ? '_' : '-';

		$str = preg_replace( '/[' . preg_quote( $dividers ) . ']+/u',     $sep, $str );
		$str = preg_replace( '/[^' . preg_quote( $sep ) . '\pL\pN\s]+/u', $sep, $str );
		$str = preg_replace( '/[' . preg_quote( $sep ) . '\s]+/u',        $sep, $str );

		return trim( strtolower( $str ), $sep );
	}

	/**
	 * Checks if a string starts with another string.
	 *
	 * @since 1.0.0
	 */
	public static function startsWith( string $str, string $starts ): bool
	{
		return substr( $str, 0, strlen( $starts ) ) === $starts;
	}

	/**
	 * Returns part of a string based on the start and length parameters.
	 *
	 * @since 1.0.0
	 */
	public static function substr( string $str, int $start, ?int $length = null ): string
	{
		return mb_substr( $str, $start, $length, 'UTF-8' );
	}

	/**
	 * Returns an excerpt of a string by limiting its number of words.
	 *
	 * @since 1.0.0
	 */
	public static function words( string $str, int $limit = 50, string $more = '&hellip;' ): string
	{
		$limit = $limit > 1 ? $limit - 1 : $limit;
		preg_match( '/^\s*+(?:\S++\s*+){1,' . $limit . '}/u', $str, $matches );

	        if ( ! isset( $matches[0] ) ) {
	    	    return $str;
	        }

		return trim( $matches[0] ) . $more;
	}
}
