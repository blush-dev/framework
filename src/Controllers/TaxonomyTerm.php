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

class TaxonomyTerm extends Controller {

	public function __invoke( array $params = [] ) {
		$this->params = $params;
		$types = App::resolve( 'content/types' );

		$path       = $this->getParameter( 'path' );
		$name       = $this->getParameter( 'name' );
		$number     = $this->getParameter( 'number' );

		// Get the taxonomy's content type.
		$taxonomy = $types->get( $this->getParameter( 'taxonomy' ) );
		$collect  = $types->get( $taxonomy->termCollect() );

		// Get the term name.
		$term = trim( str_replace( $taxonomy->path(), '', $name ), '/' );

		$current  = $this->params['number'] ?? 1;
		$per_page = posts_per_page();

		// Query the taxonomy term.
		$query = new Query( $taxonomy->path(), [ 'slug' => $term ] );

		// Query the term's content collection.
		$collection = new Query( $collect->path(), [
			'noindex'    => true,
			'number'     => $per_page,
			'offset'     => $per_page * ( intval( $current ) - 1 ),
			'order'      => 'desc',
			'orderby'    => 'filename',
			'meta_key'   => $taxonomy->type(),
			'meta_value' => $term
		] );

		if ( $query->all() && $collection->all() ) {

			return $this->response(
				$this->view( 'collection', [
					$taxonomy->type() . "-{$term}",
					$taxonomy->type() . '-term',
					'taxonomy-term'
				], [
					'title'   => $query->first()->title(),
					'query'   => $query->first(),
					'page'    => isset( $this->params['number'] ) ? intval( $this->params['number'] ) : 1,
					'entries' => $collection
				] )
			);
		}

		// If all else fails, return a 404.
		$controller = new Error404();
		return $controller( $params );
	}
}
