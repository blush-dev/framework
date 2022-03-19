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
use Blush\{App, Cache};
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
	protected string $cache_key = '';

	protected ?int $content_time = null;
	protected ?int $cache_time = null;

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
	public function setPath( string $path ): void
	{
		// Remove slashes and dots from the left/right sides.
		$path = trim( $path, '/.' );

		if ( $path ) {
			$this->path = Str::appendPath( $this->path, $path );
		}

		// Replace slash with dot for cache key.
		$this->cache_key = str_replace( '/' , '.', $path ?: 'index' );
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
		$store = Cache::store( 'content' );

		if ( ! $this->cache ) {
			$cache = $store->get( $this->cache_key );

			if ( $cache ) {
				$this->cache = $cache;
			}

			return $this->cache;
		}

		// On first run, check the temporary cache, let's see if there
		// is a persistent cache.
		if ( $this->cache && is_null( $this->content_time ) && is_null( $this->cache_time ) ) {

			// Get persistent cache.
			$cache = $store->get( $this->cache_key );

			// If the persistent cache exists, let's see if it needs
			// refreshing based on file modified times.
			if ( $cache ) {
				$this->content_time = filemtime( $this->path );
				$this->cache_time   = filemtime( $store->filepath( $this->cache_key ) );

				// If there are no modified file times or the
				// content folder is newer than the cache, delete
				// the current cache. Also, remove the cache
				// from the cache repository.
				if (
					false === $this->cache_time ||
					false === $this->content_time ||
					$this->content_time > $this->cache_time
				) {
					$store->forget( $this->cache_key );
					$this->cache = [];
				}
			}
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
		Cache::store( 'content' )->put( $this->cache_key, $data );

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
		$exclude = config( 'cache.content_exclude_meta' );
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
