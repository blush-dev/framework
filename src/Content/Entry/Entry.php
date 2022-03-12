<?php
/**
 * Base content entry class.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Content\Entry;

use Blush\Proxies\App;
use Blush\Content\Query;
use Blush\Tools\Str;

abstract class Entry {

	/**
	 * Entry content type.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    null|\Blush\Content\Types\Type
	 */
	protected $type = null;

	/**
	 * Entry content.
	 *
	 * @since  1.0.0
	 * @access protected
	 * @var    string
	 */
	protected $content = '';

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
	 * Returns the entry URI.
	 *
	 * @since  1.0.0
	 * @access public
	 * @return string
	 */
	public function uri() {
		$uri = $this->meta( 'uri' );
		return $uri ?: '';
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

			$entries = new Query( [
				'path' => $path,
				'slug' => $slugs
			] );

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
