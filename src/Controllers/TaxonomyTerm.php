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

	public function __invoke( array $params = [] ) {
		$types = App::resolve( 'content/types' );

		$name   = $params['name'] ?? '';
		$number = $params['number'] ?? 0;
		$path   = Str::beforeLast( $params['path'] ?? '', "/{$name}" );

		$current  = $number ?: 1;
		$per_page = posts_per_page();

		// Get the taxonomy's content type.
		$taxonomy = $types->getTypeFromPath( $path );
		$collect  = $types->get( $taxonomy->termCollect() );

		// Query the taxonomy term.
		$query = new Query( $taxonomy->path(), [ 'slug' => $name ] );

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

		if ( $query->all() && $collection->all() ) {
			$type_name = sanitize_with_dashes( $taxonomy->type() );

			return $this->response(
				$this->view( [
					"collection-{$type_name}-{$name}",
					"collection-{$type_name}-term",
					'collection-term',
					'collection'
				], [
					'title'   => $query->first()->title(),
					'query'   => $query->first(),
					'page'    => $number ? intval( $number ) : 1,
					'entries' => $collection
				] )
			);
		}

		// If all else fails, return a 404.
		$controller = new Error404();
		return $controller( $params );
	}
}
