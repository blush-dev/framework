<?php
/**
 * Abstract file entry class.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Content\Entry;

// Abstracts.
use Blush\Contracts\Content\Query as QueryContract;
use Blush\Contracts\Content\Type;

// Concretes.
use Blush\{App, Config, Query, Url};
use Blush\Tools\{Media, Str};

abstract class File extends Entry
{
	/**
	 * Entry path info.
	 *
	 * @since 1.0.0
	 */
	protected array $pathinfo = [];

	/**
	 * Sets up the object state. Child classes need to overwrite this and
	 * pull content and metadata from the file path.
	 *
	 * @since 1.0.0
	 */
	public function __construct( protected string $filepath )
	{
		$this->pathinfo = pathinfo( $filepath );
	}

	/**
	 * Returns the entry file path.
	 *
	 * @since 1.0.0
	 */
	protected function filepath(): string
	{
		return $this->filepath;
	}

	/**
	 * Returns the file's pathinfo or a specific value.
	 *
	 * @since  1.0.0
	 */
	protected function pathinfo( string $key = '' ): array|string
	{
		if ( $key ) {
			return $this->pathinfo[ $key ] ?? '';
		}

		return $this->pathinfo;
	}

	/**
	 * Returns the file's directory name.
	 *
	 * @since 1.0.0
	 */
	protected function dirname(): string
	{
		return $this->pathinfo( 'dirname' );
	}

	/**
	 * Returns the file's basename (includes extension).
	 *
	 * @since 1.0.0
	 */
	protected function basename(): string
	{
		return $this->pathinfo( 'basename' );
	}

	/**
	 * Returns the file's extension.
	 *
	 * @since 1.0.0
	 */
	protected function extension(): string
	{
		return $this->pathinfo( 'extension' );
	}

	/**
	 * Returns the filename without extension.
	 *
	 * @since 1.0.0
	 */
	protected function filename(): string
	{
		return $this->pathinfo( 'filename' );
	}

	/**
	 * Returns the entry type.
	 *
	 * @since 1.0.0
	 */
	public function type(): Type
	{
		// Return type if it's already set.
		if ( $this->type ) {
			return $this->type;
		}

		$types    = App::get( 'content.types' );
		$has_type = false;

		// Strip the file basename and content path from the file path.
		// This should give us the content type path, which we can match
		// against registered content types.
		$path = Str::beforeLast( $this->filepath(), basename( $this->filepath() ) );
		$path = Str::afterLast( $path, App::get( 'path.content' ) );
		$path = Str::trimSlashes( $path );

		// Get the content type by path.
		if ( $path ) {
			$has_type = $types->getTypeFromPath( $path );
		}

		// Set type or fall back to the `page` type.
		$this->type = $has_type ?: $types->get( 'page' );

		return $this->type;
	}

	/**
	 * Returns the entry name (slug).
	 *
	 * @since 1.0.0
	 */
	public function name(): string
	{
		// Get the filename without the extension.
		$name = $this->filename();

		// Strip anything before potential ordering dot, e.g.,
		// `01.{$name}`, `02.{$name}`, etc.
		if ( Str::contains( $name, '.' ) ) {
			$name =  Str::afterLast( $name, '.' );
		}

		// If the name is 'index', let's base it on the directory.
		if ( 'index' === $name && Str::contains( $this->filepath(), '/' ) ) {
			$path  = str_replace( content_path(), '', $this->filepath() );
			$_name = Str::trimSlashes( Str::beforeLast( $path, '/index' ) );
			$name = $_name ?: $name;
		}

		return $name;
	}

	/**
	 * Returns a post's visibility. Currently, the API allows for
	 * `public` and `hidden`.
	 *
	 * @since 1.0.0
	 */
	public function visibility(): string
	{
		return Str::startsWith( $this->filename(), '_' )
		       ? 'hidden'
		       : parent::visibility();
	}

	/**
	 * Returns the entry URL.
	 *
	 * @since  1.0.0
	 */
	public function url(): string
	{
		// If dealing with a page, build the URL from the filepath.
		if ( 'page' === $this->type()->name() ) {

			// Strip the content path from the directory name.
			$url_path = Str::afterLast( $this->dirname(), content_path() );

			// If this is not the `index` file, append the filename
			// to the URL path.
			if ( 'index' !== $this->filename() ) {
				$url_path = Str::appendPath(
					$url_path,
					$this->filename()
				);
			}

			return Url::to( $url_path );
		}

		// If this is the index file, it is the content type archive,
		// so we'll just return early with the type's URL.
		if ( 'index' === $this->filename() ) {
			return $this->type->url();
		}

		// Adds the required name param.
		$params = [ 'name' => $this->name() ];

		// Get `published` meta and add back-compat for `date`.
		$date = $this->metaSingle( 'published' );
		$date = $date ?: $this->metaSingle( 'date' );

		// Adds date-based params if we have a date.
		if ( $date ) {
			$timestamp = is_numeric( $date ) ? $date : strtotime( $date );
			$date      = date( 'Y-m-d', $timestamp );
			$time      = date( 'H:i:s', $timestamp );

			$params['year']   = Str::beforeFirst( $date, '-' );
			$params['month']  = Str::between( $date, '-', '-' );
			$params['day']    = Str::afterLast( $date, '-' );
			$params['hour']   = Str::beforeFirst( $time, ':' );
			$params['minute'] = Str::between( $time, ':', ':' );
			$params['second'] = Str::afterLast( $time, ':' );
		}

		// Add author param if author exists.
		if ( $author = $this->metaSingle( 'author' ) ) {
			$params['author'] = sanitize_slug( $author );
		}

		// Add content type/taxonomy params if they exist.
		foreach ( App::get( 'content.types' ) as $type ) {
			if ( $slug = $this->metaSingle( $type->name() ) ) {
				$params[ $type->name() ] = $slug;
			}
		}

		// Build the URL based on the route.
		return $this->type()->singleUrl( $params );
	}

	/**
	 * Returns the entry updated datetime.
	 *
	 * @since 1.0.0
	 */
	public function updated( string $format = '' ): string
	{
		// Returned updated meta if found.
		if ( $updated = parent::updated( 'updated', $format ) ) {
			return $updated;
		}

		// Fall back to file's modified time.
		return $this->date( filemtime( $this->filepath() ) );
	}
}
