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

use Blush\Proxies\App;
use Blush\Cache;
use Blush\Tools\Str;
use Symfony\Component\Yaml\Yaml;

function app( $abstract = '', $params = [] ) {
	return App::resolve( $abstract ?: 'app', $params );
}

function config( $name, $key = '' ) {
	$config = app( 'config' )->get( $name );

	if ( $key ) {
		return $config[ $key ] ?? null;
	}

	return $config;
}

function path( string $append = '' ) {
	return Str::appendPath( app( 'path' ), $append );
}

function uri( string $append = '' ) {
	return Str::appendUri( app( 'uri' ), $append );
}

function public_path( string $append = '' ) {
	return Str::appendPath( app( 'path.public' ), $append );
}

function resource_path( string $append = '' ) {
	return Str::appendPath( app( 'path.resource' ), $append );
}

function storage_path( string $append = '' ) {
	return Str::appendPath( app( 'path.storage' ), $append );
}

function cache_path( string $append = '' ) {
	return Str::appendPath( app( 'path.cache' ), $append );
}

function user_path( string $append = '' ) {
	return Str::appendPath( app( 'path.user' ), $append );
}

function content_path( string $append = '' ) {
	return Str::appendPath( app( 'path.content' ), $append );
}

function media_path( string $append = '' ) {
	return Str::appendPath( app( 'path.media' ), $append );
}

function public_uri( string $append = '' ) {
	return Str::appendUri( app( 'uri.public' ), $append );
}

function resource_uri( string $append = '' ) {
	return Str::appendUri( app( 'uri.resource' ), $append );
}

function storage_uri( string $append = '' ) {
	return Str::appendUri( app( 'uri.storage' ), $append );
}

function user_uri( string $append = '' ) {
	return Str::appendUri( app( 'uri.user' ), $append );
}

function content_uri( string $append = '' ) {
	return Str::appendUri( app( 'uri.content' ), $append );
}

function media_uri( string $append = '' ) {
	return Str::appendUri( app( 'uri.media' ), $append );
}

function e( $value, $double_encode = true ) {
	return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8', $double_encode );
}

function sanitize_slug( $slug ) {
	return sanitize_with_dashes( $slug );
}

function sanitize_with_dashes( $slug ) {

	//$slug = preg_replace( '/^[A-Za-z0-9]/i', '', $slug );
	$slug = strip_tags( $slug );
	$slug = strtolower( $slug );
	$slug = str_replace( [
		' ',
		'_',
		'\s',
		'/',
		'\\'
	], '-', $slug );

	return $slug;
}

function posts_per_page() {
	return 10;
}

function cache() {
	return App::resolve( 'cache' );
}

function cache_get_make( string $name, string $type = 'collection' ) {
	$cache = cache();

	if ( $cache->has( $name ) ) {
		return $cache->get( $name )->get();
	}

	cache_add( $name, $type );

	$_cache = $cache->get( $name );
	$_cache->make();

	return $_cache->get();
}

function cache_get_add( string $name, string $type = 'collection' ) {
	$cache = cache();

	if ( $cache->has( $name ) ) {
		return $cache->get( $name )->get();
	}

	cache_add( $name, $type );

	$_cache = $cache->get( $name );

	return $_cache->get();
}

function cache_get( string $name ) {
	$cache = cache();
	return $cache->has( $name ) ? $cache->get( $name )->get() : false;
}

function cache_set( string $name, $data, string $type = 'collect' ) {
	$cache = cache();

	if ( ! $cache->has( $name ) ) {
		cache_add( $name, $type );
	}

	$cache->get( $name )->set( $data );
}

function cache_add( string $name, string $type = 'collection' ) {
	$cache = cache();

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

function cache_delete( string $name ) {
	$cache = cache();

	if ( $cache->has( $name ) ) {
		$cache->get( $name )->delete();
	}
}
