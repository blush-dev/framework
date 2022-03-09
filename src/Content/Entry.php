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

	protected $filename;
	protected $path;
	protected $pathinfo;
	protected $content;
	protected $datetime;
	protected $type;
	protected $meta = [];
	protected $resolved_meta = [];

	public function __construct( $filename ) {

		$markdown = App::resolve( 'markdown' )->convert(
			file_get_contents( $filename )
		);

		$this->filename = $filename;
		$this->pathinfo = pathinfo( $filename );
		$this->meta     = $markdown->frontMatter();
		$this->content  = $markdown->content();
	}

	public function filename() {
		return $this->filename;
	}

	public function type() {
		return $this->type;
	}

	public function content() {
		return $this->content;
	}

	public function meta( string $name = '' ) {
		if ( $name ) {
			return $this->meta[ $name ] ?? false;
		}

		return $this->meta;
	}

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

	public function title() {
		return $this->meta( 'title' );
	}

	public function subtitle() {
		return $this->meta( 'subtitle' );
	}

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

	public function author() {
		$authors = $this->authors();

		return $authors ? array_shift( $authors ) : '';
	}

	public function authors() {
		return $this->metaEntries( 'author' );
	}

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

	public function uri() {

		$uri = $this->meta( 'uri' );

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

	public function excerpt( $length = 40, $more = '&hellip;' ) {

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
