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
use Blush\Tools\Str;

class Locator {

	/**
	 * Relative path to the content folder where to locate content.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $path;

	/**
	 * Array of cached filenames and metadata.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    array
	 */
	protected $cache;

	/**
	 * Relative path to the cache folder where to store located content.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $cache_path = 'content';

	/**
	 * Sets up object state. The path is relative to the user content
	 * folder. If no value is passed in, it will be the root.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  string  $path
	 * @return void
	 */
	public function __construct( string $path = '' ) {

		// Remove slashes and dots from the left/right sides.
		$path = trim( $path, '/.' );

		$this->path = App::resolve( 'path.content' );

		if ( $path ) {
			$this->path       = Str::appendPath( $this->path, $path );
			$this->cache_path = Str::appendPath( $this->cache_path, $path );
		}
	}

	/**
	 * Returns the folder path relative to the content directory.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return string
	 */
	public function path() {
		return $this->path;
	}

	/**
	 * Returns the cached filenames and metadata.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @return array
	 */
	protected function getCache() {

		if ( ! $this->cache ) {
			$cache = cache_get_add( $this->cache_path, 'collection' );
			$this->cache = $cache ? $cache->all() : [];
		}

		return $this->cache;
	}

	/**
	 * Caches filenames and metadata.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @param  array  $data
	 * @return void
	 */
	protected function setCache( $data ) {
		cache_set( $this->cache_path, $data, 'collection' );

		$this->cache = $data;
	}

	/**
	 * Returns collection of located files as an array. The filenames are
	 * the array keys and the metadata is the value.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return array
	 */
	public function all() {
		$entries = $this->getCache();

		if ( ! $entries ) {
			$entries = $this->locate();
		}

		$located = [];

		foreach ( (array) $entries as $filename => $data ) {
			$filename = Str::appendPath( $this->path, $filename );
			$located[ $filename ] = $data;
		}

		return $located;
	}

	/**
	 * Locates content files and returns them as an array with the filename
	 * as the key and the metadata as the value.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @return array
	 */
	protected function locate() {
		$files = glob( Str::appendPath( $this->path, '*.md' ) );

		if ( ! $files ) {
			return [];
		}

		$cache = [];

		// Get the metadata keys to exclude from the cache.
		$exclude = config( 'cache', 'content_exclude_meta' );
		$exclude = is_array( $exclude ) ? array_flip( $exclude ) : false;

		foreach ( $files as $file ) {

			// Skip if the file isn't Markdown.
			if ( ! is_file( $file ) || 'md' !== pathinfo( $file, PATHINFO_EXTENSION ) ) {
				continue;
			}

			$data     = [];
			$contents = file_get_contents( $file );

			if ( $contents ) {
				$markdown = App::resolve( 'markdown' )->convert( $contents );

				$data = $markdown->frontMatter();

				if ( $exclude ) {
					$data = array_diff_key( $data, $exclude );
				}
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
