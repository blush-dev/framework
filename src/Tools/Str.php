<?php

namespace Blush\Tools;

class Str {

	// @todo DIRECTORY_SEPARATOR
	public static function appendPath( string $path, string $append = '' ) {
		$path   = rtrim( $path, '/\\' );
		$append = ltrim( $append, '/\\' );
		return $append ? "{$path}/{$append}" : $path;
	}

	public static function appendUri( string $uri, string $append = '' ) {
		$uri    = rtrim( $uri, '/\\' );
		$append = ltrim( $append, '/\\' );
		return $append ? "{$uri}/{$append}" : $uri;
	}

	public static function slashBefore( $str ) {

		return '/' . ltrim( $str, '/' );
	}

	public static function slashAfter( $str ) {

		return rtrim( $str, '/' ) . '/';
	}

	public static function slashTrim( $str ) {

		return trim( $str, '/' );
	}

	public static function startsWith( $str, $starts ) {

		return substr( $str, 0, strlen( $starts ) ) === $starts;
	}

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

	public static function substr( $str, $start, $length = null ) {
		return mb_substr( $str, $start, $length, 'UTF-8' );
	}

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
}
