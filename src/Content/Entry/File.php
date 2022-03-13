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

use Blush\Proxies\App;
use Blush\Content\Types\Type;
use Blush\Tools\Str;

abstract class File extends Entry {

	/**
	 * Entry filename.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $filename;

	/**
	 * Entry path info.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    array
	 */
	protected $pathinfo;

	/**
	 * Sets up the object state. Child classes need to overwrite this and
	 * pull content and metadata from the file.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  string  $filename
	 * @return void
	 */
	public function __construct( $filename ) {
		$this->filename = $filename;
		$this->pathinfo = pathinfo( $filename );
	}

	/**
	 * Returns the entry type.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return Type
	 */
	public function type() : Type
	{

		// Return type if it's already set.
		if ( $this->type ) {
			return $this->type;
		}

		$types = App::get( 'content/types' );

		// Strip the file basename and content path from the filename.
		// This should give us the content type path, which we can match
		// against registered content types.
		$path = Str::beforeLast( $this->filename(), basename( $this->filename() ) );
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
	 * Returns the entry filename.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return string
	 */
	public function filename() {
		return $this->filename;
	}

	/**
	 * Returns the entry name (basename).
	 *
	 * @since  1.0.0
	 * @access public
	 * @return string
	 */
	public function name() {
		// Get the filename without the extension.
		$name = $this->pathinfo['filename'];

		// Strip anything after potential ordering dot, e.g.,
		// `01.{$name}.md`, `02.{$name}.md`, etc.
		if ( Str::contains( $name, '.' ) ) {
			$name =  Str::afterLast( $name, '.' );
		}

		return $name;
	}

	/**
	 * Returns the entry URI.
	 *
	 * @todo   Massive cleanup.
	 * @since  1.0.0
	 * @access public
	 * @return string
	 */
	public function uri() : string
	{
		$uri       = $this->type()->singleUri();
		$name      = $this->name();
		$timestamp = false;

		if ( $date = $this->date() ) {
			$timestamp = strtotime( $date );
		}

		if ( Str::contains( $uri, '{name}' ) ) {
			$uri = str_replace( '{name}', $name, $uri );
		}

		if (  $timestamp && Str::contains( $uri, '{year}' ) ) {
			$uri = str_replace( '{year}', date( 'Y', $timestamp ), $uri );
		}

		if (  $timestamp && Str::contains( $uri, '{month}' ) ) {
			$uri = str_replace( '{month}', date( 'm', $timestamp ), $uri );
		}

		if (  $timestamp && Str::contains( $uri, '{day}' ) ) {
			$uri = str_replace( '{day}', date( 'd', $timestamp ), $uri );
		}

		return \uri( $uri );
	}
}
