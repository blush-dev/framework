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
use Blush\Tools\Str;

class ContentTypeArchive extends Controller {

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

		$path   = $params['path'] ?? '';
		$number = $params['number'] ?? '';

		if ( $number ) {
			$path = Str::beforeLast( $path, "/page" );
		}

		$current  = $number ?: 1;
		$per_page = posts_per_page();

		// Get the content type.
		$type     = $types->getTypeFromPath( $path );
		$collect  = $types->get( $type->collect() );

		// Query the content type.
		$query = new Query( $type->path(), [ 'slug' => 'index' ] );

		// Query the content type collection.
		$entries = new Query( $collect->path(), [
			'noindex'    => true,
			'number'     => $per_page,
			'offset'     => $per_page * ( intval( $current ) - 1 ),
			'order'      => 'desc',
			'orderby'    => 'filename'
		] );

		if ( $query->all() && $entries->all() ) {
			$views = [
				'collection-' . sanitize_with_dashes( $type->type() )
			];

			if ( $type->isTaxonomy() ) {
				$views[] = 'collection-taxonomy-' . sanitize_with_dashes( $type->type() );
				$views[] = 'collection-taxonomy';
			}

			$views[] = 'collection';

			return $this->response(
				$this->view( $views, [
					'query'   => $query->first(),
					'title'   => $query->first()->title(),
					'page'    => $number ? intval( $number ) : 1,
					'entries' => $entries
				] )
			);
		}

		// If all else fails, return a 404.
		return $this->forward( Error404::class, $params );
	}
}
