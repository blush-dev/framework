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

use Blush\App;
use Blush\Content\Query;
use Blush\Template\Tags\{DocumentTitle, Pagination};
use Blush\Tools\Str;
use Symfony\Component\HttpFoundation\Response;

class CollectionTaxonomyTerm extends Controller
{
	/**
	 * Callback method when route matches request.
	 *
	 * @since 1.0.0
	 */
	public function __invoke( array $params = [] ) : Response
	{
		$types = App::resolve( 'content/types' );

		$name   = $params['name'] ?? '';
		$number = $params['number'] ?? '';
		$path   = $params['path'];

		$type_path = Str::beforeLast( $params['path'] ?? '', "/{$name}" );

		if ( $number ) {
			$path = Str::beforeLast( $path , "/page/{$number}" );
		}

		$current  = $number ?: 1;
		$per_page = posts_per_page();
		$args     = [];

		// Get the taxonomy's content type.
		$taxonomy = $types->getTypeFromPath( $type_path );
		$collect  = $types->get( $taxonomy->termCollect() );

		// Query the taxonomy term.
		$single = ( new Query( [
			'path' => $taxonomy->path(),
			'slug' => $name
		] ) )->single();

		// Gets query vars from entry meta.
		if ( $single ) {
			$args = $single->metaArr( 'collection' );
			$args = $args ?: [];
			// Needed to calculate the offset.
			$per_page = $args['number'] ?? $per_page;
		}

		// Query the term's content collection.
		$collection = new Query( array_merge( [
			'path'       => $collect->path(),
			'noindex'    => true,
			'number'     => $per_page,
			'offset'     => $per_page * ( intval( $current ) - 1 ),
			'order'      => 'desc',
			'orderby'    => 'filename',
			'meta_key'   => $taxonomy->type(),
			'meta_value' => $name
		], $args ) );

		if ( $single && $collection->all() ) {
			$type_name = sanitize_slug( $taxonomy->type() );

			$doctitle = new DocumentTitle( $single->title(), [
				'page' => $number ?: 1
			] );

			$pagination = new Pagination( [
				'basepath' => $path,
				'current'  => $number ?: 1,
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
		return $this->forward404( $params );
	}
}
