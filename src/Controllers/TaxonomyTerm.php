<?php
/**
 * Taxonomy term controller.
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

class TaxonomyTerm extends Controller {

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

		$name   = $params['name'] ?? '';
		$number = $params['number'] ?? '';
		$path   = Str::beforeLast( $params['path'] ?? '', "/{$name}" );

		$current  = $number ?: 1;
		$per_page = posts_per_page();

		// Get the taxonomy's content type.
		$taxonomy = $types->getTypeFromPath( $path );
		$collect  = $types->get( $taxonomy->termCollect() );

		// Query the taxonomy term.
		$single = new Query( $taxonomy->path(), [ 'slug' => $name ] );

		// Query the term's content collection.
		$collection = new Query( $collect->path(), [
			'noindex'    => true,
			'number'     => $per_page,
			'offset'     => $per_page * ( intval( $current ) - 1 ),
			'order'      => 'desc',
			'orderby'    => 'filename',
			'meta_key'   => $taxonomy->type(),
			'meta_value' => $name
		] );

		if ( $single->all() && $collection->all() ) {
			$type_name = sanitize_with_dashes( $taxonomy->type() );

			return $this->response(
				$this->view( [
					"collection-{$type_name}-{$name}",
					"collection-{$type_name}-term",
					'collection-term',
					'collection'
				], [
					'title'      => $single->first()->title(),
					'single'     => $single->first(),
					'collection' => $collection,
					'query'      => $single->first(),
					'entries'    => $collection,
					'page'       => $number ? intval( $number ) : 1
				] )
			);
		}

		// If all else fails, return a 404.
		return $this->forward( Error404::class, $params );
	}
}
