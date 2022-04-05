<?php
/**
 * File-based content entry.
 *
 * Sub-classes need to at least override the constructor to set up the content
 * and metadata properties. This class should be able to handle most file types
 * outside of those two properties.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Content\Entry;

use Blush\{App, Url};
use Blush\Content\Types\Type;
use Blush\Tools\Str;

abstract class File extends Entry
{
	/**
	 * Entry path info.
	 *
	 * @since 1.0.0
	 */
	protected array $pathinfo;

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
		$path = Str::beforeLast( $this->filePath(), basename( $this->filePath() ) );
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
	 * Returns the entry URL.
	 *
	 * @since  1.0.0
	 */
	public function url():  string
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

		// Let the parent class handle non-page URLs.
		return parent::url();
	}

	/**
	 * Returns the entry file path.
	 *
	 * @since 1.0.0
	 */
	public function filePath(): string
	{
		return $this->filepath;
	}

	/**
	 * Returns the file's pathinfo or a specific value.
	 *
	 * @since  1.0.0
	 */
	public function pathinfo( string $key = '' ): array|string
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
	public function dirname(): string
	{
		return $this->pathinfo( 'dirname' );
	}

	/**
	 * Returns the file's basename (includes extension).
	 *
	 * @since 1.0.0
	 */
	public function basename(): string
	{
		return $this->pathinfo( 'basename' );
	}

	/**
	 * Returns the file's extension.
	 *
	 * @since 1.0.0
	 */
	public function extension(): string
	{
		return $this->pathinfo( 'extension' );
	}

	/**
	 * Returns the filename without extension.
	 *
	 * @since 1.0.0
	 */
	public function filename(): string
	{
		return $this->pathinfo( 'filename' );
	}

	/**
	 * Returns the entry name (basename).
	 *
	 * @since 1.0.0
	 */
	public function name(): string
	{
		// Get the filename without the extension.
		$name = $this->filename();

		// Strip anything after potential ordering dot, e.g.,
		// `01.{$name}.md`, `02.{$name}.md`, etc.
		if ( Str::contains( $name, '.' ) ) {
			$name =  Str::afterLast( $name, '.' );
		}

		return $name;
	}

	/**
	 * If an entry filename begins with an underscore (e.g., `_example.md`),
	 * then we consider it hidden. Otherwise, the entry is public.
	 *
	 * @since 1.0.0
	 */
	public function visibility(): string
	{
		if ( $this->metaSingle( 'visibility' ) ) {
			return parent::visibility();
		}

		return Str::startsWith( $this->filename(), '_' ) ? 'hidden' : 'public';
	}
}
