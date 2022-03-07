<?php
/**
 * Content filesystem locator.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Content;

use Blush\Proxies\App;

class Locator {

	protected $path;

	protected $cache;

	protected $cache_path = 'content';

	public function __construct( $path = '' ) {

		// Remove slashes and dots from the left/right sides.
		$path = trim( $path, '/.' );

		$this->path = App::resolve( 'path/content' );

		if ( $path ) {
			$this->path .= "/{$path}";
		}

		// Use the path to build the cache key/name, such as
		// `{$cache_path}.json`.
		if ( $path ) {
			$this->cache_path .= "/{$path}";
		}
	}

	public function path() {
		return $this->path;
	}

	protected function getCache() {

		if ( ! $this->cache ) {
			$cache = cache_get( $this->cache_path, 'collection' );
			$this->cache = $cache ? $cache->all() : [];
		}

		return $this->cache;
	}

	protected function setCache( $data ) {
		cache_set( $this->cache_path, $data, 'collection' );

		$this->cache = $data;
	}

	public function all() {

		$entries = $this->getCache();

		if ( ! $entries ) {
			$entries = $this->locate();
		}

		$located = [];

		foreach ( (array) $entries as $filename => $data ) {

			$filename = "{$this->path}/{$filename}";

			$located[ $filename ] = $data;
		}

		return $located;
	}

	protected function locate() {

		//$files = glob( App::resolve( 'path' ) . "/{$this->path}/*.md" );
		$files = glob( "{$this->path}/*.md" );

		if ( ! $files ) {
			return [];
		}

		$cache = [];

		// Get the metadata keys that we want to cache.
		//$cache_meta_keys = array_flip( App::resolve( 'cache/meta' ) );

		foreach ( $files as $file ) {

			if ( ! is_file( $file ) || 'md' !== pathinfo( $file, PATHINFO_EXTENSION ) ) {
				continue;
			}

			$data     = [];
			$contents = file_get_contents( $file );

			if ( $contents ) {

				$markdown = App::resolve( 'markdown' )->convert( $contents );

				$_data = $markdown->frontMatter();

				$data = $_data;

				// only cache the data that we need.
				//$data = array_intersect_key( $_data, $cache_meta_keys );
			}

			$filename = str_replace( "{$this->path}/", '', $file );

			$cache[ $filename ] = $data;
		}

		if ( $cache ) {
			$this->setCache( $cache );
			return $cache;
		}

		return [];
	}
}
