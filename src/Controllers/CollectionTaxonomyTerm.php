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

use Blush\{App, Query};
use Blush\Template\Tags\{DocumentTitle, Pagination};
use Blush\Tools\Str;
use Symfony\Component\HttpFoundation\{Request, Response};

class CollectionTaxonomyTerm extends Controller
{
	/**
	 * Callback method when route matches request.
	 *
	 * @since 1.0.0
	 */
	public function __invoke( array $params = [], Request $request ): Response
	{
		$types = App::get( 'content.types' );

		$path = $params['path'];
		$name = $params['name'];
		$page = $params['page'] ? intval( $params['page'] ) : 1;

		$type_path = Str::beforeLast( $path, "/{$name}" );

		if ( Str::contains( $path, "/page/{$page}" ) ) {
			$path = Str::beforeFirst( $path, "/page/{$page}" );
		}

		// Get the taxonomy's content type.
		$type    = $types->getTypeFromPath( $type_path );
		$collect = $types->get( $type->termCollect() );

		// Query the taxonomy term.
		$single = Query::make( [
			'path' => $type->path(),
			'slug' => $name
		] )->single();

		// Merge the default collection query args for the type
		// with user query args.
		$query_args = array_merge(
			$type->termCollectionArgs(),
			$single ? $single->collectionArgs() : []
		);

		// Set required variables for the query.
		$query_args['number'] = $query_args['number'] ?? 10;
		$query_args['offset'] = $query_args['number'] * ( $page - 1 );

		// Query the term's content collection.
		$collection = Query::make( array_merge( $query_args, [
			'meta_key'   => $type->type(),
			'meta_value' => $name
		] ) );

		if ( $single && $single->isPublic() && $collection->all() ) {
			$type_name = sanitize_slug( $type->type() );

			$doctitle = new DocumentTitle( $single->title(), [
				'page' => $page
			] );

			$pagination = new Pagination( [
				'basepath' => $path,
				'current'  => $page,
				'total'    => $collection->pages()
			] );

			return $this->response( $this->view(
				array_merge( $single->viewPaths(), [
					"collection-{$type_name}-{$name}",
					"collection-{$type_name}-term",
					'collection-term',
					'collection',
					'index'
				] ), [
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
