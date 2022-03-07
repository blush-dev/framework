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

class ContentTypeArchive extends Controller {

	public function __invoke( array $params = [] ) {
		$this->params = $params;
		$types = App::resolve( 'content/types' );

		$path   = $this->getParameter( 'path' );
		$name   = $this->getParameter( 'name' );
		$number = $this->getParameter( 'number' );

		$current  = $number ?? 1;
		$per_page = posts_per_page();

		// Get the content type.
		$type     = $types->getTypeFromPath( $name );
		$collect  = $types->get( $type->collect() );

		// Query the content type.
		$query = new Query( $type->path(), [ 'slug' => 'index' ] );

		// Query the content type collection.
		$collection = new Query( $collect->path(), [
			'noindex'    => true,
			'number'     => $per_page,
			'offset'     => $per_page * ( intval( $current ) - 1 ),
			'order'      => 'desc',
			'orderby'    => 'filename'
		] );

		if ( $query->all() && $collection->all() ) {
			$view_slugs = [ sanitize_with_dashes( $type->type() ) ];

			if ( $type->isTaxonomy() ) {
				$view_slugs[] = 'taxonomy-' . sanitize_with_dashes( $type->type() );
				$view_slugs[] = 'taxonomy';
			}

			return $this->response(
				$this->view( 'collection', $view_slugs, [
					'query'   => $query->first(),
					'title'   => $query->first()->title(),
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
