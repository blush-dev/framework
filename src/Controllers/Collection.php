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

use Blush\{App, Query};
use Blush\Template\Tags\{DocumentTitle, Pagination};
use Blush\Tools\Str;
use Symfony\Component\HttpFoundation\{Request, Response};

class Collection extends Controller
{
	/**
	 * Callback method when route matches request.
	 *
	 * @since 1.0.0
	 */
	public function __invoke( array $params = [], Request $request ): Response
	{
		$types = App::resolve( 'content.types' );

		$path   = $params['path'] ?? '';
		$number = $params['number'] ?? '';

		if ( $number ) {
			$path = Str::beforeLast( $path, "/page" );
		}

		$current  = $number ?: 1;
		$per_page = posts_per_page();
		$args     = [];

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
		$single = Query::make( [
			'path' => $type->path(),
			'slug' => 'index'
		] )->single();

		// Gets query vars from entry meta.
		if ( $single ) {
			$args = $single->metaArr( 'collection' );
			$args = $args ?: [];
			// Needed to calculate the offset.
			$per_page = $args['number'] ?? $per_page;
		}

		// Query the content type collection.
		$collection = Query::make( array_merge( [
			'path'    => $collect->path(),
			'noindex' => true,
			'number'  => $per_page,
			'offset'  => $per_page * ( intval( $current ) - 1 ),
			'order'   => 'desc',
			'orderby' => 'filename'
		], $args ) );

		if ( $single && $collection->all() ) {
			$type_name  = sanitize_slug( $type->type() );
			$model_name = $type->isTaxonomy() ? 'taxonomy' : 'content';

			$doctitle = new DocumentTitle( $single->title(), [
				'page' => $number ?: 1
			] );

			$pagination = new Pagination( [
				'basepath' => $path,
				'current'  => $current,
				'total'    => $collection->pages()
			] );

			return $this->response( $this->view( [
				"collection-{$type_name}",
				"collection-{$model_name}",
				'collection',
				'index'
			], [
				'doctitle'   => $doctitle,
				'pagination' => $pagination,
				'single'     => $single,
				'collection' => $collection
			] ) );
		}

		// If all else fails, return a 404.
		return $this->forward404( $params );
	}
}
