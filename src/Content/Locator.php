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

// Interfaces.
use Blush\Contracts\Content\Locator as LocatorContract;

// Classes.
use Blush\App;
use Blush\Tools\Str;
use Symfony\Component\Yaml\Yaml;

class Locator implements LocatorContract
{
	/**
	 * Relative path to the content folder where to locate content.
	 *
	 * @since 1.0.0
	 */
	protected string $path = '';

	/**
	 * Array of cached filenames and metadata.
	 *
	 * @since 1.0.0
	 */
	protected array $cache = [];

	/**
	 * Relative path to the cache folder where to store located content.
	 *
	 * @since 1.0.0
	 */
	protected string $cache_path = 'content';

	/**
	 * Sets up object state.
	 *
	 * @since 1.0.0
	 */
	public function __construct( string $path = '' )
	{
		$this->path = App::resolve( 'path.content' );

		if ( $path ) {
			$this->setPath( $path );
		}
	}

	/**
	 * Sets the locator path. The path is relative to the user content
	 * folder. If no value is passed in, it will be the root.
	 *
	 * @since 1.0.0
	 */
	public function setPath( string $path ) : void
	{
		// Remove slashes and dots from the left/right sides.
		$path = trim( $path, '/.' );

		if ( $path ) {
			$this->path       = Str::appendPath( $this->path, $path );
			$this->cache_path = Str::appendPath( $this->cache_path, $path );
		}
	}

	/**
	 * Returns the folder path relative to the content directory.
	 *
	 * @since 1.0.0
	 */
	public function path() : string
	{
		return $this->path;
	}

	/**
	 * Returns the cached filenames and metadata.
	 *
	 * @since 1.0.0
	 */
	protected function getCache() : array
	{
		if ( ! $this->cache ) {
			$cache = cache_get_add( $this->cache_path, 'collection' );
			$this->cache = $cache ? $cache->all() : [];
		}

		return $this->cache;
	}

	/**
	 * Caches filenames and metadata.
	 *
	 * @since 1.0.0
	 */
	protected function setCache( array $data ) : void
	{
		cache_set( $this->cache_path, $data, 'collection' );
		$this->cache = $data;
	}

	/**
	 * Returns collection of located files as an array. The filenames are
	 * the array keys and the metadata is the value.
	 *
	 * @since 1.0.0
	 */
	public function all() : array
	{
		$entries = $this->getCache();

		if ( ! $entries ) {
			$entries = $this->locate();
		}

		$located = [];

		foreach ( $entries as $basename => $data ) {
			$filepath = Str::appendPath( $this->path, $basename );
			$located[ $filepath ] = $data;
		}

		return $located;
	}

	/**
	 * Locates content files and returns them as an array with the filename
	 * as the key and the metadata as the value.
	 *
	 * @since 1.0.0
	 */
	protected function locate() : array
	{
		$filepaths = glob( Str::appendPath( $this->path, '*.md' ) );

		if ( ! $filepaths ) {
			return [];
		}

		$cache = [];

		// Get the metadata keys to exclude from the cache.
		$exclude = config( 'cache', 'content_exclude_meta' );
		$exclude = is_array( $exclude ) ? array_flip( $exclude ) : false;

		foreach ( $filepaths as $filepath ) {

			// Skip if the file isn't Markdown.
			if ( ! is_file( $filepath ) || 'md' !== pathinfo( $filepath, PATHINFO_EXTENSION ) ) {
				continue;
			}

			// Set up empty array in case there's no data.
			$data = [];

			// Get the first 8 kb of data from the file.  We're only
			// grabbing the frontmatter. Anything even encroaching
			// this number would be insane.
			$content = file_get_contents(
				$filepath, false, null, 0, 8 * 1024
			);

			if ( $content ) {
				// Grab the YAML frontmatter from the file.
		                preg_match(
					'/^---[\r\n|\r|\n](.*?)[\r\n|\r|\n]---/s',
					$content,
					$match
				);

				// If frontmatter found, parse it.
		                if ( $match ) {
		                        $data = Yaml::parse( $match[1] );
		                }

				// Exclude meta from cache.
				if ( $exclude ) {
					$data = array_diff_key( $data, $exclude );
				}
			}

			// Remove the content dir path. We only need the basename.
			$key = str_replace( "{$this->path}/", '', $filepath );

			$cache[ $key ] = $data;
		}

		if ( $cache ) {
			$this->setCache( $cache );
			return $cache;
		}

		return [];
	}
}
