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

use Blush\App;
use Blush\Content\Query;
use Blush\Template\Tags\{DocumentTitle, Pagination};
use Blush\Tools\Str;
use Symfony\Component\HttpFoundation\Response;

class Collection extends Controller
{
	/**
	 * Callback method when route matches request.
	 *
	 * @since 1.0.0
	 */
	public function __invoke( array $params = [] ) : Response
	{
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
			return $this->forward404( $params );
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
			'path'       => $collect->path(),
			'noindex'    => true,
			'number'     => $per_page,
			'offset'     => $per_page * ( intval( $current ) - 1 ),
			'order'      => 'desc',
			'orderby'    => 'filename'
		], $args ) );

		if ( $single->all() && $collection->all() ) {
			$type_name  = sanitize_with_dashes( $type->type() );
			$model_name = $type->isTaxonomy() ? 'taxonomy' : 'content';

			$doctitle = new DocumentTitle( $single->first()->title(), [
				'page' => $number ?? 1
			] );

			$pagination = new Pagination( [
				'basepath' => $path,
				'current'  => $current,
				'total'    => ceil( $collection->total() / $collection->number() )
			] );

			return $this->response( $this->view( [
				"collection-{$type_name}",
				"collection-{$model_name}",
				'collection',
				'index'
			], [
				'doctitle'   => $doctitle,
				'pagination' => $pagination,
				'single'     => $single->first(),
				'collection' => $collection
			] ) );
		}

		// If all else fails, return a 404.
		return $this->forward404( $params );
	}
}
