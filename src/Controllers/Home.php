<?php
/**
 * Home controller.
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
use Symfony\Component\HttpFoundation\Response;

class Home extends Controller {

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
		$alias = \config( 'app', 'home_alias' );
		$type  = false;

		// Checks if the homepage has an alias content type and if the
		// type exists.
		if ( $alias && $types->has( $alias ) ) {
			$type    = $types->get( $alias );
			$collect = $types->get( $type->collect() );
		}

		// If we have a content type and a collection type, run query.
		if ( $type && $collect ) {
			$current  = $params['number'] ?? 1;
			$per_page = posts_per_page();

			// Query the content type.
			$single = new Query( $type->path(), [ 'slug' => 'index' ] );

			// Query the content type collection.
			$collection = new Query( $collect->path(), [
				'noindex'    => true,
				'number'     => $per_page,
				'offset'     => $per_page * ( intval( $current ) - 1 ),
				'order'      => 'desc',
				'orderby'    => 'filename'
			] );

			if ( $single->all() && $collection->all() ) {
				return $this->response(
					$this->view( [
						'collection-home',
						sprintf(
							'collection-%s',
							sanitize_with_dashes( $type->type() )
						),
						'collection',
						'index'
					], [
						'title'      => \config( 'app', 'title' ),
						'single'     => $single->first(),
						'collection' => $collection,
						'page'       => intval( $current )
					] )
				);
			}
		}

		// Query the homepage `index.md` file.
		$single = new Query( '', [ 'slug' => 'index' ] );

		if ( $single->all() ) {
			return $this->response(
				$this->view( [
					'single-page-home',
					'single-home',
					'home', // @todo - remove.
					'single-page',
					'single'
				], [
					'title'      => $single->first()->title(),
					'single'     => $single->first(),
					'collection' => $single,
					'query'      => $single->first(),
					'entries'    => $single,
					'page'       => 1
				] )
			);
		}

		return new Response( 'No user/content/index.md file found.' );
	}
}
