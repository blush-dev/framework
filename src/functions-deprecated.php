<?php

if ( ! function_exists( 'uri' ) ) {
	/**
	 * Returns app URI with optionsal appended path.
	 *
	 * @since 1.0.0
	 */
	function uri( string $append = '' ) : string
	{
		return url( '', $append );
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
		return app_url( $append );
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
		return config_url( $append );
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
		return public_url( $append );
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
		return view_url( $append );
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
		return resource_url( $append );
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
		return storage_url( $append );
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
		return cache_url( $append );
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
		return user_url( $append );
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
		return content_url( $append );
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
		return media_url( $append );
	}
}
