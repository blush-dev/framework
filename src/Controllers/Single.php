<?php
/**
 * Single controller.
 *
 * @package   Blush
 * @author    Justin Tadlock <justintadlock@gmail.com>
 * @copyright Copyright (c) 2018 - 2022, Justin Tadlock
 * @link      https://github.com/blush-dev/framework
 * @license   https://opensource.org/licenses/MIT
 */

namespace Blush\Controllers;

use Blush\{App, Query};
use Blush\Template\Tags\DocumentTitle;
use Blush\Tools\Str;
use Symfony\Component\HttpFoundation\{Request, Response};

class Single extends Controller
{
	/**
	 * Callback method when route matches request.
	 *
	 * @since 1.0.0
	 */
	public function __invoke( array $params, Request $request ): Response
	{
		$types = App::resolve( 'content.types' );

		// Check if another content type is part of the request. In
		// particular, this is mostly used for taxonomies used in the URL.
		foreach ( $types as $type ) {
			if ( isset( $params[ $type->name() ] ) ) {
				$meta_key   = $type->name();
				$meta_value = $params[ $type->name() ];
			}
		}

		// Get the post name and path.
		$name = $params['name'];
		$path = $params['path'] ?? '';
		$parts = explode( '/', $path );

		// Explodes the path into parts and loops through each. Strips
		// the last part off the original path with each iteration and
		// checks if it's the path or URI for the content type. If a
		// match is found, break out of the loop.
		foreach ( array_reverse( $parts ) as $part ) {
			$path = Str::beforeLast( $path, "/{$part}" );

			// Check type by path and URI.
			if ( $type = $types->getTypeFromPath( $path ) ) {
				break;
			} elseif ( $type = $types->getTypeFromUri( $path ) ) {
				break;
			}
		}

		$single = Query::make( [
			'path'       => $type ? $type->path() : $path,
			'slug'       => $name,
			'year'       => $params['year']   ?? null,
			'month'      => $params['month']  ?? null,
			'day'        => $params['day']    ?? null,
			'author'     => $params['author'] ?? null,
			'meta_key'   => $meta_key         ?? null,
			'meta_value' => $meta_value       ?? null
		] )->single();

		if ( $single && $single->isPublic() ) {
			$type_name  = sanitize_slug( $type->type() );
			$collection = false;

			if ( $args = $single->collectionArgs() ) {
				$collection = Query::make( $args );
			}

			$doctitle = new DocumentTitle( $single->title() );

			return $this->response( $this->view(
				array_merge( $single->viewPaths(), [
					"single-{$type_name}-{$name}",
					"single-{$type_name}",
					'single',
					'index'
				] ), [
					'doctitle'   => $doctitle,
					'pagination' => false,
					'single'     => $single,
					'collection' => $collection
				]
			) );
		}

		// If all else fails, return a 404.
		return $this->forward404( $params, $request );
	}
}
