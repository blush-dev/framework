<?php
/**
 * Content entry.
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

class Entry {

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
	 * Entry content.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $content;

	/**
	 * Entry content type.
	 *
	 * @todo   This is not implemented yet.
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $type;

	/**
	 * Stores the entry metadata (front matter).
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    array
	 */
	protected $meta = [];

	/**
	 * Resolved metadata, which represent content type relationships, such
	 * as taxonomy terms.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    array
	 */
	protected $resolved_meta = [];

	/**
	 * Sets up the object state.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  string  $filename
	 * @return void
	 */
	public function __construct( $filename ) {

		$markdown = App::resolve( 'markdown' )->convert(
			file_get_contents( $filename )
		);

		$this->filename = $filename;
		$this->pathinfo = pathinfo( $filename );
		$this->meta     = $markdown->frontMatter();
		$this->content  = $markdown->content();
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
	 * Returns the entry type.
	 *
	 * @todo   Implementation needed.
	 * @since  1.0.0
	 * @access public
	 * @return string
	 */
	public function type() {
		return $this->type;
	}

	/**
	 * Returns the entry content.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return string
	 */
	public function content() {
		return $this->content;
	}

	/**
	 * Returns entry metadata.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  string  $name
	 * @return mixed
	 */
	public function meta( string $name = '' ) {
		if ( $name ) {
			return $this->meta[ $name ] ?? false;
		}

		return $this->meta;
	}

	/**
	 * Returns queried content type entries stored as meta.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  string  $name
	 * @param  string  $path
	 * @return array
	 */
	public function metaEntries( string $name, string $path = '' ) {

		if ( ! $path ) {
			$path = $name;
		}

		if ( isset( $this->resolved_meta[ $name ] ) ) {
			return $this->resolved_meta[ $name ];
		}

		$this->resolved_meta[ $name ] = [];

		if ( $meta_values = $this->meta( $name ) ) {

			$slugs = [];

			foreach ( (array) $meta_values as $value ) {
				$slugs[] = sanitize_slug( $value );
			}

			$entries = new Query( $path, [ 'slug' => $slugs ] );

			if ( $entries->all() ) {
				$this->resolved_meta[ $name ] = $entries->all();
			}
		}

		return $this->resolved_meta[ $name ];
	}

	/**
	 * Returns the entry title.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return string
	 */
	public function title() {
		return $this->meta( 'title' );
	}

	/**
	 * Returns the entry subtitle.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return string
	 */
	public function subtitle() {
		return $this->meta( 'subtitle' );
	}

	/**
	 * Returns the entry date.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return string
	 */
	public function date() {
		if ( ! $this->meta( 'date' ) ) {
			return '';
		}

		$timestamp = is_numeric( $this->meta( 'date' ) )
		             ? $this->meta( 'date' )
			     : strtotime( $this->meta( 'date' ) );

		// @todo - config/site.yaml
		return date( 'F j, Y', $timestamp );
	}

	/**
	 * Returns the entry author.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return string
	 */
	public function author() {
		$authors = $this->authors();
		return $authors ? array_shift( $authors ) : '';
	}

	/**
	 * Returns the entry authors.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return string
	 */
	public function authors() {
		return $this->metaEntries( 'author' );
	}

	/**
	 * Returns a taxonomy entries.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  string  $taxonomy
	 * @return array
	 */
	public function terms( $taxonomy ) {
		$content_types = App::resolve( 'content/types' );

		if ( $content_types->has( $taxonomy ) ) {
			return $this->metaEntries(
				$taxonomy,
		 		$content_types->get( $taxonomy )->path()
			);
		}

		return [];
	}

	/**
	 * Returns the entry URI.
	 *
	 * @todo   Massive cleanup.
	 * @since  1.0.0
	 * @access public
	 * @return string
	 */
	public function uri() {
		$uri = $this->meta( 'uri' );

		// @todo check for http. If not, prepend site URI.
		if ( ! $uri ) {
			$uri = $this->meta( 'slug' );
		}

		$filename = $this->pathinfo['basename'];

		$parts = explode( '.', $filename );
		array_pop( $parts );
		if ( 1 < count( $parts ) ) {
			array_shift( $parts );
		}
		$uri = join( '', $parts );

		$path = str_replace(
			[
				App::resolve( 'path' ) . '/user/content/',
				$this->pathinfo['basename']
			],
			'',
			$this->filename
		);

		$path = trim( $path, '/' );

		$uri = 'index' === $uri ? '' : "/{$uri}";

		return Str::appendUri( App::resolve( 'uri' ), $path . $uri );
	}

	/**
	 * Returns the entry excerpt.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  int    $length
	 * @param  string $more
	 * @return string
	 */
	public function excerpt( int $length = 40, string $more = '&hellip;' ) {

		$content = $this->content();

		if ( $this->meta( 'excerpt' ) ) {
			return App::resolve( 'markdown' )->convert(
				$this->meta( 'excerpt' )
			)->content();
		}

		$excerpt = strip_tags( trim( $content ) );
		$words = str_word_count( $excerpt, 2 );
		if ( count( $words ) > $length ) {
			$words = array_slice( $words, 0, $length, true );
			end( $words );
			$position = key( $words ) + strlen( current( $words ) );
			$excerpt = substr( $excerpt, 0, $position ) . $more;
		}
		return $excerpt ? '<p>' . $excerpt . '</p>' : '';
	}
}
