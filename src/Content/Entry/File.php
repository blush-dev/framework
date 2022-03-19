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

use Blush\App;
use Blush\Content\Types\Type;
use Blush\Tools\Str;

abstract class File extends Entry
{
	/**
	 * Entry file path.
	 *
	 * @since 1.0.0
	 */
	protected string $filepath;

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
	public function __construct( string $filepath )
	{
		$this->filepath = $filepath;
		$this->pathinfo = pathinfo( $filepath );
	}

	/**
	 * Returns the entry type.
	 *
	 * @since 1.0.0
	 */
	public function type() : Type
	{
		// Return type if it's already set.
		if ( $this->type ) {
			return $this->type;
		}

		$types = App::get( 'content.types' );

		// Strip the file basename and content path from the file path.
		// This should give us the content type path, which we can match
		// against registered content types.
		$path = Str::beforeLast( $this->filePath(), basename( $this->filePath() ) );
		$path = Str::afterLast( $path, App::get( 'path.content' ) );
		$path = Str::slashTrim( $path );

		// Get the content type by path.
		if ( $path ) {
			$this->type = $types->getTypeFromPath( $path );
		}

		// If no type, fall back to the `page` type.
		if ( ! $this->type ) {
			$this->type = $types->get( 'page' );
		}

		return $this->type;
	}

	/**
	 * Returns the entry file path.
	 *
	 * @since 1.0.0
	 */
	public function filePath() : string
	{
		return $this->filepath;
	}

	/**
	 * Returns the file's pathinfo or a specific value.
	 *
	 * @since  1.0.0
	 * @return array|string
	 */
	public function pathinfo( string $key = '' )
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
	public function dirname() : string
	{
		return $this->pathinfo( 'dirname' );
	}

	/**
	 * Returns the file's basename (includes extension).
	 *
	 * @since 1.0.0
	 */
	public function basename() : string
	{
		return $this->pathinfo( 'basename' );
	}

	/**
	 * Returns the file's extension.
	 *
	 * @since 1.0.0
	 */
	public function extension() : string
	{
		return $this->pathinfo( 'extension' );
	}

	/**
	 * Returns the filename without extension.
	 *
	 * @since 1.0.0
	 */
	public function filename() : string
	{
		return $this->pathinfo( 'filename' );
	}

	/**
	 * Returns the entry name (basename).
	 *
	 * @since 1.0.0
	 */
	public function name() : string
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
