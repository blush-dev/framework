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
use Blush\Template\Hierarchy;
use Blush\Template\Tags\{DocumentTitle, Pagination};
use Blush\Tools\Str;
use Symfony\Component\HttpFoundation\{Request, Response};

class Collection extends Controller
{
	/**
	 * Callback method when route matches request.
	 *
	 * @since  1.0.0
	 */
	public function __invoke( array $params, Request $request ): Response
	{
		// Get all content types.
		$types = App::get( 'content.types' );

		// Get needed URI params from the router.
		$path = $params['path'];
		$page = intval( $params['page'] ?? 1 );

		// If this is a paged view, strip the page from the path.
		if ( Str::contains( $path, "/page/{$page}" ) ) {
			$path = Str::beforeFirst( $path, "/page/{$page}" );
		}

		// Get the content type from the path or URI.
		$type = $types->getTypeFromPath( $path ) ?: $types->getTypeFromUri( $path );

		// Bail if there is no type.
		if ( ! $type ) {
			return $this->forward404( $params, $request );
		}

		// Get the collection type.
		$collect = $types->get( $type->collect() );

		// Query the content type's index file.
		$single = Query::make( [
			'path' => $type->path(),
			'slug' => 'index'
		] )->single();

		// Merge the default collection query args for the type
		// with user query args.
		$query_args = array_merge(
			$type->collectionArgs(),
			$single ? $single->collectionArgs() : []
		);

		// Set required variables for the query.
		$query_args['number'] = $query_args['number'] ?? 10;
		$query_args['offset'] = $query_args['number'] * ( $page - 1 );

		// Query the content type collection.
		$collection = Query::make( $query_args );

		if ( $single && $collection->hasEntries() ) {

			$type_name  = sanitize_slug( $type->type() );
			$model_name = $type->isTaxonomy() ? 'taxonomy' : 'content';

			$doctitle = new DocumentTitle( $single->title(), [
				'page' => $page
			] );

			$pagination = new Pagination( [
				'basepath' => $path,
				'current'  => $page,
				'total'    => $collection->pages()
			] );

			return $this->response( $this->view(
				Hierarchy::collection( $single ),
				[
					'doctitle'   => $doctitle,
					'pagination' => $pagination,
					'single'     => $single,
					'collection' => $collection
				]
			) );
		}

		// If all else fails, return a 404.
		return $this->forward404( $params, $request );
	}
}
