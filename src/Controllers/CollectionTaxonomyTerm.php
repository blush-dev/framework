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

		$name = $params['name'] ?? '';
		$page = $params['page'] ?? '';
		$path = $params['path'];

		$type_path = Str::beforeLast( $params['path'] ?? '', "/{$name}" );

		if ( $page ) {
			$path = Str::beforeLast( $path , "/page/{$page}" );
		}

		// Get the taxonomy's content type.
		$type    = $types->getTypeFromPath( $type_path );
		$collect = $types->get( $type->termCollect() );

		// Query the taxonomy term.
		$single = Query::make( [
			'path' => $type->path(),
			'slug' => $name
		] )->single();

		// Get the default collection query args for the type.
		$query_args = $type->termCollectionArgs();

		// Get user collection query args and merge if there are any.
		if ( $single && $args = $single->metaArr( 'collection' ) ) {
			$query_args = array_merge( $query_args, $args );
		}

		// Set required variables for the query.
		$page = $page ? abs( intval( $page ) ) : 1;
		$query_args['number'] = $query_args['number'] ?? 10;
		$query_args['offset'] = $query_args['number'] * ( $page - 1 );

		// Query the term's content collection.
		$collection = Query::make( array_merge( $query_args, [
			'meta_key'   => $type->type(),
			'meta_value' => $name
		] ) );

		if ( $single && $collection->all() ) {
			$type_name = sanitize_slug( $type->type() );

			$doctitle = new DocumentTitle( $single->title(), [
				'page' => $page
			] );

			$pagination = new Pagination( [
				'basepath' => $path,
				'current'  => $page,
				'total'    => $collection->pages()
			] );

			return $this->response( $this->view( [
				"collection-{$type_name}-{$name}",
				"collection-{$type_name}-term",
				'collection-term',
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
		return $this->forward404( $params, $request );
	}
}
