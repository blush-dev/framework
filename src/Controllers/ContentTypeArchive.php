<?php
/**
 * Content type archive controller.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Controllers;

use Blush\Proxies\App;
use Blush\Content\Query;
use Blush\Tools\Str;

class ContentTypeArchive extends Controller {

	/**
	 * Callback method when route matches request.
	 *
	 * @since  1.0.0
	 * @access public
	 * @param  array  $params
	 * @return Response
	 */
	public function __invoke( array $params = [] ) {
		$types = App::resolve( 'content/types' );

		$path   = $params['path'] ?? '';
		$number = $params['number'] ?? '';

		if ( $number ) {
			$path = Str::beforeLast( $path, "/page" );
		}

		$current  = $number ?: 1;
		$per_page = posts_per_page();

		// Get the content by path.
		$type = $types->getTypeFromPath( $path );

		// If no match for the path, try the URI.
		if ( ! $type ) {
			$type = $types->getTypeFromUri( $path );
		}

		// Bail if there is no type.
		if ( ! $type ) {
			return $this->forward( Error404::class, $params );
		}

		// Get the collection type.
		$collect = $types->get( $type->collect() );

		// Query the content type.
		$single = new Query( [
			'path' => $type->path(),
			'slug' => 'index'
		] );

		if ( $single->all() ) {
			$args = $single->first()->meta( 'collection' );
			$args = $args ?: [];
			// Needed to calculate the offset.
			$per_page = $args['number'] ?? $per_page;
		}

		// Query the content type collection.
		$collection = new Query( array_merge( [
			'path' => $collect->path(),
			'noindex'    => true,
			'number'     => $per_page,
			'offset'     => $per_page * ( intval( $current ) - 1 ),
			'order'      => 'desc',
			'orderby'    => 'filename'
		], $args ) );

		if ( $single->all() && $collection->all() ) {
			$type_name = sanitize_with_dashes( $type->type() );
			$views = [ "collection-{$type_name}" ];

			if ( $type->isTaxonomy() ) {
				$views[] = 'collection-taxonomy';
			}

			return $this->response(
				$this->view( array_merge( $views, [
					'collection',
					'index'
				] ), [
					'title'      => $single->first()->title(),
					'single'     => $single->first(),
					'collection' => $collection,
					'page'       => $number ? intval( $number ) : 1
				] )
			);
		}

		// If all else fails, return a 404.
		return $this->forward( Error404::class, $params );
	}
}
