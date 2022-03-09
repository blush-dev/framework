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

class Str {

	/**
	 * Trims slashes and appends a path to a path.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  string  $path
	 * @param  string  $append
	 * @return string
	 */
	public static function appendPath( string $path, string $append = '' ) {
		$path   = rtrim( $path, '/\\' );
		$append = ltrim( $append, '/\\' );
		return $append ? "{$path}/{$append}" : $path;
	}

	/**
	 * Trims slashes and appends a path to a URI.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  string  $path
	 * @param  string  $append
	 * @return string
	 */
	public static function appendUri( string $uri, string $append = '' ) {
		$uri    = rtrim( $uri, '/\\' );
		$append = ltrim( $append, '/\\' );
		return $append ? "{$uri}/{$append}" : $uri;
	}

	/**
	 * Adds a slash before a string.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  string  $str
	 * @return string
	 */
	public static function slashBefore( $str ) {
		return '/' . ltrim( $str, '/\\' );
	}
	/**
	 * Adds a slash after a string.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  string  $str
	 * @return string
	 */
	public static function slashAfter( $str ) {
		return rtrim( $str, '/\\' ) . '/';
	}

	/**
	 * Trims slashes from both sides of string.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  string  $str
	 * @return string
	 */
	public static function slashTrim( $str ) {
		return trim( $str, '/\\' );
	}

	/**
	 * Checks if a string starts with another string.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  string  $str
	 * @param  string  $starts
	 * @return bool
	 */
	public static function startsWith( $str, $starts ) {
		return substr( $str, 0, strlen( $starts ) ) === $starts;
	}

	/**
	 * Replaces the last occurrence of a string.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  string  $search
	 * @param  string  $replace
	 * @param  string  $str
	 * @return string
	 */
	public static function replaceLast( $search, $replace, $str ) {
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
	 * Returns part of a string based on the start and length parameters.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  string  $str
	 * @param  string  $start
	 * @param  string  $length
	 * @return string
	 */
	public static function substr( $str, $start, $length = null ) {
		return mb_substr( $str, $start, $length, 'UTF-8' );
	}

	/**
	 * Returns part of a string after the last occurrence.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  string  $str
	 * @param  string  $search
	 * @return string
	 */
	public static function afterLast( $str, $search ) {
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
	 * Returns part of a string before the last occurrence.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  string  $str
	 * @param  string  $search
	 * @return string
	 */
	public static function beforeLast( $str, $search ) {
		if ( '' === $search ) {
			return $str;
		}

		$pos = mb_strrpos( $str, $search );

		if ( false === $pos ) {
			return $str;
		}

		return static::substr( $str, 0, $pos );
	}
}
