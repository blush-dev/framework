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

use Blush\App;
use Blush\Content\Query;
use Blush\Template\Tags\DocumentTitle;
use Blush\Tools\Str;
use Symfony\Component\HttpFoundation\Response;

class Single extends Controller
{
	/**
	 * Callback method when route matches request.
	 *
	 * @since 1.0.0
	 */
	public function __invoke( array $params = [] ) : Response
	{
		$types = App::resolve( 'content/types' );

		// Get the post name and path.
		$name = $params['name'] ?? '';
		$path = Str::beforeLast( $params['path'] ?? '', "/{$name}" );

		// Get the content type by path.
		$type = $types->getTypeFromPath( $path );

		// Look for a `path/{$name}.md` file.
		$single = new Query( [
			'path' => $path,
			'slug' => $name
		] );

		if ( $single->all() ) {
			$type_name    = sanitize_with_dashes( $type->type() );
			$collection   = false;
			$collect_args = $single->first()->meta( 'collection' );

			if ( $collect_args ) {
				$collection = new Query( $collect_args );
			}

			$doctitle = new DocumentTitle( $single->first()->title() );

			return $this->response( $this->view( [
				"single-{$type_name}-{$name}",
				"single-{$type_name}",
				'single',
				'index'
			], [
				'doctitle'   => $doctitle,
				'pagination' => false,
				'single'     => $single->first(),
				'collection' => $single
			] ) );
		}

		// If all else fails, return a 404.
		return $this->forward404( $params );
	}
}
