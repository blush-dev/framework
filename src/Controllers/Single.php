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
	public function __invoke( array $params = [], Request $request ): Response
	{
		$types = App::resolve( 'content.types' );

		// Get the post name and path.
		$name = $params['name'] ?? '';
		$path = Str::beforeLast( $params['path'] ?? '', "/{$name}" );

		// If the post name begins with `_`, it is private.
		if ( Str::startsWith( $name, '_' ) ) {
			return $this->forward404( $params, $request );
		}

		// Get the content type by path.
		$type = $types->getTypeFromPath( $path );

		$single = Query::make( [
			'path' => $path,
			'slug' => $name
		] )->single();

		if ( $single ) {
			$type_name  = sanitize_slug( $type->type() );
			$collection = false;

			if ( $args = $single->metaArr( 'collection' ) ) {
				$collection = Query::make( $args );
			}

			$doctitle = new DocumentTitle( $single->title() );

			return $this->response( $this->view( [
				"single-{$type_name}-{$name}",
				"single-{$type_name}",
				'single',
				'index'
			], [
				'doctitle'   => $doctitle,
				'pagination' => false,
				'single'     => $single,
				'collection' => $collection
			] ) );
		}

		// If all else fails, return a 404.
		return $this->forward404( $params, $request );
	}
}
