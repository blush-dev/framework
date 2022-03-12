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
use Blush\Template\Tags\DocumentTitle;
use Blush\Template\Tags\Pagination;
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
				'path'    => $collect->path(),
				'noindex' => true,
				'number'  => $per_page,
				'offset'  => $per_page * ( intval( $current ) - 1 ),
				'order'   => 'desc',
				'orderby' => 'filename'
			], $args ) );

			if ( $single->all() && $collection->all() ) {
				$type_name = sanitize_with_dashes( $type->type() );

				$doctitle = new DocumentTitle( '', [ 'page' => $current ] );

				$pagination = new Pagination( [
					'base'    => '',
					'current' => $current,
					'total'   => ceil( $collection->total() / $collection->number() )
				] );

				return $this->response(
					$this->view( [
						'collection-home',
						"collection-{$type_name}",
						'collection',
						'index'
					], [
						'doctitle'   => $doctitle,
						'pagination' => $pagination,
						'single'     => $single->first(),
						'collection' => $collection
					] )
				);
			}
		}

		// Query the homepage `index.md` file.
		$single = new Query( [
			'path' => '',
			'slug' => 'index'
		] );

		if ( $single->all() ) {
			$collection   = false;
			$collect_args = $single->first()->meta( 'collection' );

			if ( $collect_args ) {
				$collection = new Query( $collect_args );
			}

			return $this->response(
				$this->view( [
					'single-home',
					'single-page',
					'single',
					'index'
				], [
					'doctitle'   => new DocumentTitle(),
					'pagination' => false,
					'single'     => $single->first(),
					'collection' => $collection ?: false
				] )
			);
		}

		return new Response( 'No user/content/index.md file found.' );
	}
}
